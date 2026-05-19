
/** =========[ إعداد عام ]========= **/
const SHEET_NAME = 'Sheet1'; // اتركه null ليستخدم الورقة الحالية أو ضع اسمها كنص

const MONTH_HEADERS = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December'
];

const TRIGGER_HOUR = 9;     // 09:00
const TRIGGER_MINUTE = 5;   // +5 دقائق

// مفاتيح خصائص لتخزين آخر النصوص المستخدمة في الواجهات
const KEY_LAST_SENDALL = 'LAST_SENDALL_TEXT';
const KEY_LAST_BYMONTH = 'LAST_BYMONTH_TEXT';

// مفاتيح خصائص للتحكم في الإرسال
const KEY_HALT = 'BG_HALT'; // '1' لإيقاف الإرسال الفوري

// مفاتيح حالة مهمة الإرسال التلقائي (عام لجميع الأنواع)
const KEY_JOB_ACTIVE = 'BG_JOB_ACTIVE';       // '1' شغّال، '0' متوقف
const KEY_JOB_TYPE   = 'BG_JOB_TYPE';         // 'send_all' | 'first_friday' | 'mid_month' | 'by_month_cond'
const KEY_JOB_TEXT   = 'BG_JOB_TEXT';         // نص الرسالة (قالب)
const KEY_JOB_NEXT_ID= 'BG_JOB_NEXT_ID';      // التالي للبدء منه (id)
const KEY_JOB_TAG    = 'BG_JOB_TAG';          // وسم اختياري للّوج
const KEY_JOB_TRIGGER_SET = 'BG_JOB_TRIGGER_SET'; // لتفادي إنشاء تريغر مكرر
const KEY_JOB_MONTH  = 'BG_JOB_MONTH';        // للشغل الشهري/الفلترة
const KEY_JOB_COND   = 'BG_JOB_COND';         // 'empty' | 'x' (للإرسال حسب شهر/شرط)

/** =========[ إعدادات استئناف عامة ]========= **/
function getResumeDelayMinutes_(){
  const v = Number(getProps_().getProperty('RESUME_DELAY_MINUTES') || 65);
  return (isNaN(v) || v <= 0) ? 65 : v;
}
function getRetryShortMinutes_(){
  const v = Number(getProps_().getProperty('RETRY_SHORT_MINUTES') || 3);
  return (isNaN(v) || v < 1) ? 3 : v;
}
// أقصى عدد رسائل في كل تشغيل (لتجنّب مهلة GAS)
function getMaxPerTick_(){
  const v = Number(getProps_().getProperty('MAX_PER_TICK') || 0); // 0 = غير مفعّل
  return (isNaN(v) || v < 0) ? 0 : v;
}
// حفظ تقدّم كل N رسائل أثناء الحلقة
function checkpointEveryN_(){
  const v = Number(getProps_().getProperty('CHECKPOINT_EVERY') || 25);
  return (isNaN(v) || v < 5) ? 25 : v;
}
// فرض الإرسال ASCII فقط (بدون Unicode) لإطالة الرسالة
function isForceAscii_() {
  const p = PropertiesService.getScriptProperties().getProperty('FORCE_ASCII');
  return String(p || 'on').trim().toLowerCase() !== 'off'; // الافتراضي: on
}

/** =========[ أدوات عامة ]========= **/
function getProps_(){ return PropertiesService.getScriptProperties(); }
function getDocProps_(){ return PropertiesService.getDocumentProperties(); }
function withLock_(fn){
  const lock = LockService.getScriptLock();
  lock.waitLock(30000); // انتظر حتى 30 ثانية
  try { return fn(); } finally { lock.releaseLock(); }
}
function getSheet_() {
  const ss = SpreadsheetApp.getActive();
  const sh = SHEET_NAME ? ss.getSheetByName(SHEET_NAME) : ss.getActiveSheet();
  if (!sh) throw new Error('Sheet not found. تأكد من اسم الورقة أو اجعل SHEET_NAME=null.');
  return sh;
}
function getDataAndHeader_(){
  const sh = getSheet_();
  const data = sh.getDataRange().getValues();
  if (data.length < 2) throw new Error('لا توجد صفوف بيانات.');
  const header = data[0];
  return { sh, data, header };
}
function colIndex_(header, name){
  const i = header.indexOf(name);
  if (i < 0) throw new Error('العمود "' + name + '" غير موجود.');
  return i;
}
function bool_(v){
  if (v === true) return true;
  const s = String(v || '').trim().toLowerCase();
  return s === 'true' || s === '1' || s === 'yes' || s === 'y';
}
function monthHeaderForDate_(d){
  const names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const name = names[d.getMonth()];
  return MONTH_HEADERS.includes(name) ? name : null;
}
function isFirstFriday_(d){ return d.getDay() === 5 && d.getDate() <= 7; }
function isMidMonth_(d){ return d.getDate() === 15; }
// حارس وقت: نخرج قبل نهاية ~5:20 دقيقة لنحفظ التقدّم ونستأنف ذاتيًا
function willTimeoutSoon_(startedAt, seconds=320){
  return (Date.now() - startedAt) > seconds*1000;
}

/**
 * تطبيع أرقام الهواتف إلى E.164 (قدر الإمكان) لرقم NL.
 * يعالج: فراغات/شرطات/أقواس/سلاش، 0031، +0031، 031، +031، 06x، 0xxxxxxxxx، 6xxxxxxxx، الأعداد بصيغة علمية، اللاحقة .0
 */
function normalizePhone_(raw){
  let s = String(raw || '').trim();

  // تحويل scientific notation أو أرقام بنهاية .0 إلى رقم صحيح كنص
  if (/e\+|\.0$/.test(s)) {
    try { s = String(Utilities.formatString('%.0f', Number(s))); } catch(e){}
  }

  // تنظيف الشكل
  s = s.replace(/[()\-\s\.]/g, '');
  if (s.includes('/')) s = s.split('/')[0];

  // حالات شائعة لرمز NL
  if (s.startsWith('+0031')) s = '+31' + s.slice(5);
  if (s.startsWith('0031'))  s = '31' + s.slice(4);
  if (s.startsWith('+031'))  s = '+31' + s.slice(4);
  if (s.startsWith('031'))   s = '31' + s.slice(3);

  if (s.startsWith('+31')) return s;
  if (s.startsWith('31'))  return '+' + s;

  // وطني NL: 06xxxxxxxx -> +316xxxxxxxx
  if (s.startsWith('06') && s.length >= 10) return '+316' + s.slice(2);

  // وطني NL: 0xxxxxxxxx -> +31xxxxxxxxx
  if (s.startsWith('0') && s.length >= 10) return '+31' + s.slice(1);

  // جوّال NL عارٍ: 6xxxxxxxx
  if (/^6\d{8}$/.test(s)) return '+31' + s;

  if (s.startsWith('+')) return s;

  // خلاف ذلك: أعِده كما هو (لا نسقطه بصمت)
  return s;
}

/** =========[ ASCII Sanitization ]========= **/
function asciiSanitize_(text){
  if (!text) return '';
  let t = String(text);
  try {
    t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  } catch(e) {}
  const map = {'’':"'", '‘':"'", '“':'"', '”':'"', '—':'-', '–':'-', '…':'...', '€':'EUR', '£':'GBP', '•':'-'};
  t = t.replace(/[’‘“”—–…€£•]/g, m => map[m] || '');
  t = t.replace(/[^\x00-\x7F]/g, '');
  t = t.replace(/\s+/g, ' ').trim();
  return t;
}
function smsSegmentsCount_(text){
  const ascii = isForceAscii_() ? asciiSanitize_(text) : String(text||'');
  const single = 160, multi = 153;
  return ascii.length <= single ? 1 : Math.ceil(ascii.length / multi);
}

/** =========[ Rate Limiter & Halt ]========= **/
function bgShouldHalt_() { return getProps_().getProperty(KEY_HALT) === '1'; }
function bgSetHalt_(on=true) { getProps_().setProperty(KEY_HALT, on ? '1' : '0'); }
function stopAllSending(){
  bgSetHalt_(true);
  jobSetActive_(false);
  Logger.log('Sending HALTED by user + Job deactivated.');
}
function resumeSending(){
  bgSetHalt_(false);
  Logger.log('Sending RESUMED by user.');
}
function getHourKey_() {
  const d = new Date();
  return Utilities.formatDate(d, Session.getScriptTimeZone(), 'yyyyMMddHH');
}
function getMaxPerHour_(){
  const v = getProps_().getProperty('BG_MAX_PER_HOUR');
  const n = Number(v || 2500); // عدّل حسب باقتك/الموافقة الجديدة
  return isNaN(n) || n<=0 ? 2500 : n;
}
function takeHourQuota_(n) {
  return withLock_(()=>{
    const p = getProps_();
    const key = 'BG_HOUR_'+getHourKey_();
    const used = Number(p.getProperty(key) || '0');
    const maxh = getMaxPerHour_();
    if (used + n > maxh) return false;
    p.setProperty(key, String(used + n));
    return true;
  });
}
function getBatchSize_(){
  const v = Number(getProps_().getProperty('BATCH_SIZE') || 150);
  return (isNaN(v) || v<=0) ? 150 : v;
}
function getBatchSleepMs_(){
  const v = Number(getProps_().getProperty('SLEEP_BETWEEN_BATCH_MS') || 5000);
  return (isNaN(v) || v<0) ? 5000 : v;
}

/** =========[ إدارة حالة مهمة الإرسال التلقائي ]========= **/
function jobIsActive_(){ return getProps_().getProperty(KEY_JOB_ACTIVE) === '1'; }
function jobSetActive_(on){ getProps_().setProperty(KEY_JOB_ACTIVE, on ? '1' : '0'); }
function jobSet_(kv){
  withLock_(()=>{
    const p = getProps_();
    Object.keys(kv||{}).forEach(k=>p.setProperty(k, String(kv[k])));
  });
}
function jobGet_(k){ return getProps_().getProperty(k); }
function jobClear_(){
  withLock_(()=>{
    const p = getProps_();
    [ KEY_JOB_ACTIVE, KEY_JOB_TYPE, KEY_JOB_TEXT, KEY_JOB_NEXT_ID, KEY_JOB_TAG,
      KEY_JOB_TRIGGER_SET, KEY_JOB_MONTH, KEY_JOB_COND ].forEach(k=>p.deleteProperty(k));
  });
}
function scheduleOneOff_(fnName, minutesFromNow){
  const when = new Date(Date.now()+minutesFromNow*60*1000);
  ScriptApp.newTrigger(fnName).timeBased().at(when).create();
}

/** =========[ BulkGate API (Transactional) ]========= **/
/**
 * إرسال رسالة Transactional إلى رقم واحد (أو مصفوفة أرقام) بنفس النص.
 * يلتقط لمت المزوّد ويعيد حالة نصية مختصرة.
 */
function sendViaBulkGate_(phone, text, tag){
  const props = getProps_();
  const APP_ID    = props.getProperty('BULKGATE_APP_ID');
  const APP_TOKEN = props.getProperty('BULKGATE_APP_TOKEN');
  const SENDER_ID = props.getProperty('SENDER_ID') || 'gText';
  const SENDER_ID_VALUE = props.getProperty('SENDER_ID_VALUE') || 'Al Boukhari';
  const DEFAULT_COUNTRY = (props.getProperty('DEFAULT_COUNTRY') || 'NL').trim();

  if (!APP_ID || !APP_TOKEN) throw new Error('يرجى ضبط BULKGATE_APP_ID و BULKGATE_APP_TOKEN.');
  if (bgShouldHalt_()) throw new Error('HALTED_BY_USER');

  // Rate limit داخلي قبل الوصول للـ API
  if (!takeHourQuota_(1)) throw new Error('HOURLY_LIMIT_PREVENTIVE_BLOCK');

  // ASCII/Unicode
  const forceAscii = isForceAscii_();
  const textSrc = String(text||'');
  const textToSend = forceAscii ? asciiSanitize_(textSrc) : textSrc;
  const unicodeFlag = forceAscii ? false : /[^\u0000-\u007F]/.test(textToSend);

  // تجهيز الأرقام والـ country: لو فيه +E.164 نترك country null
  const numbers = Array.isArray(phone) ? phone.map(String) : [String(phone)];
  const anyHasPlus = numbers.some(n => String(n||'').startsWith('+'));
  const countryParam = anyHasPlus ? null : (DEFAULT_COUNTRY || null);

  const payload = {
    application_id: APP_ID,
    application_token: APP_TOKEN,
    number: numbers,            // array دائمًا
    text: textToSend,
    country: countryParam,      // null إذا كانت الأرقام تبدأ بـ +
    channel: {
      sms: {
        sender_id: SENDER_ID,           // غالبًا "text"
        sender_id_value: SENDER_ID_VALUE, // اسم الـ Text ID الموافق عليه
        unicode: unicodeFlag
      }
    },
    tag: tag || undefined
  };

  const url = 'https://portal.bulkgate.com/api/2.0/advanced/transactional';
  const resp = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });

  const code = resp.getResponseCode();
  const txt = resp.getContentText() || '';

  // لمت مزوّد/خدمات
  if (code === 429 || code === 503) throw new Error('REMOTE_RATE_LIMIT');
  if (/an_hourly_transaction_messages_quota_has_been_exhausted/i.test(txt)) {
    throw new Error('REMOTE_HOURLY_QUOTA_EXHAUSTED');
  }

  let statusNote = 'ERR ' + code;
  try {
    const body = JSON.parse(txt || '{}');
    if (code >= 200 && code < 300 && body.data) {
      const responses = Array.isArray(body.data.response) ? body.data.response : [];
      if (responses.length) {
        const st = String(responses[0].status || '').toLowerCase();
        statusNote = (st === 'accepted' || st === 'sent' || st === 'scheduled') ? st.toUpperCase() : ('API ' + (st || 'OK'));
      } else {
        statusNote = 'OK';
      }
    } else if (body.error || body.type) {
      statusNote = ('ERR ' + (body.type || '') + ' ' + (body.error || '')).trim();
    }
  } catch(e){ /* تجاهل parsing */ }

  if (code < 200 || code >= 300) throw new Error(statusNote || ('ERR ' + code));
  return statusNote;
}

/**
 * (اختياري) إرسال لمجموعة أرقام بنفس النص في استدعاء واحد.
 * ملاحظة: لا يدعم تخصيص الاسم لكل مستلم في نفس الاستدعاء — استخدم الإرسال الفردي عند الحاجة لـ {{Naam}}.
 */
function sendBulkTransactionalSameText_(numbers, text, tag){
  const phones = (numbers || []).map(normalizePhone_).filter(Boolean);
  if (!phones.length) return 'NO_NUMBERS';
  return sendViaBulkGate_(phones, text, tag || 'bulk-group');
}

/** =========[ Log Sheet ]========= **/
function ensureLogSheet_(){
  const ss = SpreadsheetApp.getActive();
  let log = ss.getSheetByName('SMS_Log');
  if (!log) {
    log = ss.insertSheet('SMS_Log');
    log.appendRow(['Timestamp','Type','Month','StudentID','Naam','Telefoon','Segments','Status','Message']);
  }
  return log;
}
function logSend_(type, month, id, naam, phone, text, status){
  const log = ensureLogSheet_();
  const asciiText = asciiSanitize_(text);
  log.appendRow([new Date(), type, month||'', id||'', naam||'', phone||'', smsSegmentsCount_(asciiText), status||'', asciiText]);
}

/** =========[ قوالب شهرية (هولندي ASCII) للجدولة الآلية ]========= **/
function getFirstFridayTemplate_(){
  const p = getProps_().getProperty('TEMPLATE_FIRST_FRIDAY_NL');
  const def = 'Beste ouder van {{Naam}}, Al Boukhari School groet u en herinnert u eraan om de betaling van {{month}} zo snel mogelijk te voldoen.';
  return asciiSanitize_(p || def);
}
function getMidMonthTemplate_(){
  const p = getProps_().getProperty('TEMPLATE_MID_MONTH_NL');
  const def = 'Beste familie van student {{Naam}}, betaling voor {{month}} is vertraagd. Graag zo spoedig mogelijk voldoen.';
  return asciiSanitize_(p || def);
}
function fillTemplate_(tpl, map){
  return String(tpl||'')
    .replace(/\{\{\s*(Naam|name)\s*\}\}/gi, map.Naam || '')
    .replace(/\{\{\s*(month|Month)\s*\}\}/g, map.month || '');
}

/** =========[ وظائف مساعدة للإرسال على دفعات ]========= **/
function batchControl_(sentThisRun){
  const BATCH_SIZE = getBatchSize_();
  const SLEEP_MS = getBatchSleepMs_();
  if (BATCH_SIZE > 0 && sentThisRun > 0 && (sentThisRun % BATCH_SIZE) === 0) {
    Utilities.sleep(SLEEP_MS);
  }
}

/** =========[ مُعالِجات الدُفعات (Jobs) ]========= **/
// 1) send_all
function processSendAllBatch_(){
  if (bgShouldHalt_()) { jobSetActive_(false); return { done:true, reason:'HALTED' }; }
  const type = jobGet_(KEY_JOB_TYPE);
  if (type !== 'send_all') return { done:true, reason:'NO_JOB' };

  const userText = jobGet_(KEY_JOB_TEXT) || '';
  const startFrom = Number(jobGet_(KEY_JOB_NEXT_ID) || 0) || 0;
  const tag = jobGet_(KEY_JOB_TAG) || 'send_all_auto';
  const monthHeader = jobGet_(KEY_JOB_MONTH) || monthHeaderForDate_(new Date());
  const { data, header } = getDataAndHeader_();

  const iId   = colIndex_(header, 'id');
  const iNaam = colIndex_(header, 'Naam');
  const iTel  = colIndex_(header, 'Telefoon');
  const iSMS  = colIndex_(header, 'sms');
  const iAll  = colIndex_(header, 'send_all');

  const t0 = Date.now();
  const checkpointN = checkpointEveryN_();
  const maxPerTick = getMaxPerTick_();

  let sent=0, skipped=0, errors=0, lastIdUsed=startFrom;

  for (let r=1; r<data.length; r++){
    if (bgShouldHalt_()) break;

    const rowId = Number(data[r][iId] || 0) || 0;
    if (startFrom && rowId < startFrom) { skipped++; continue; }

    const smsOk = bool_(data[r][iSMS]);
    const allOk = bool_(data[r][iAll]);
    if (!smsOk || !allOk) { skipped++; continue; }

    const phone = normalizePhone_(data[r][iTel]);
    if (!phone) { skipped++; continue; }

    const msg = fillTemplate_(String(userText||''), { Naam: data[r][iNaam], month: monthHeader });

    try{
      const status = sendViaBulkGate_(phone, msg, tag);
      logSend_('SendAll(Auto)', monthHeader, data[r][iId], data[r][iNaam], phone, msg, status);
      sent++;
      lastIdUsed = rowId;

      if (sent % checkpointN === 0) jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1, [KEY_JOB_MONTH]: monthHeader });
      if (maxPerTick && sent >= maxPerTick){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1, [KEY_JOB_MONTH]: monthHeader });
        return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId: lastIdUsed + 1 };
      }
      if (willTimeoutSoon_(t0)){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1, [KEY_JOB_MONTH]: monthHeader });
        return { done:false, reason:'TIME_BUDGET', nextId: lastIdUsed + 1 };
      }
      batchControl_(sent);

    }catch(e){
      const em = String(e.message||'');
      if (em.indexOf('HALTED')>=0) break;
      if (em.indexOf('REMOTE_RATE_LIMIT')>=0 || em.indexOf('REMOTE_HOURLY')>=0 || em.indexOf('HOURLY')>=0){
        const nextId = lastIdUsed ? (lastIdUsed+1) : (rowId+1);
        jobSet_({ [KEY_JOB_NEXT_ID]: nextId, [KEY_JOB_MONTH]: monthHeader });
        return { done:false, reason:'HIT_QUOTA', nextId };
      }
      errors++;
      logSend_('SendAll(Auto)', monthHeader, data[r][iId], data[r][iNaam], phone, msg, 'ERR '+em);
    }
  }

  // هل بقي أحد؟
  let remaining = false;
  for (let r=1; r<data.length; r++){
    const rowId = Number(data[r][iId] || 0) || 0;
    if (lastIdUsed && rowId <= lastIdUsed) continue;
    const smsOk = bool_(data[r][iSMS]);
    const allOk = bool_(data[r][iAll]);
    const phone = normalizePhone_(data[r][iTel]);
    if (smsOk && allOk && phone){ remaining = true; break; }
  }

  if (remaining){
    const nextId = lastIdUsed ? (lastIdUsed+1) : startFrom;
    jobSet_({ [KEY_JOB_NEXT_ID]: nextId, [KEY_JOB_MONTH]: monthHeader });
    return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId };
  } else {
    return { done:true, reason:'FINISHED' };
  }
}

// 2) First Friday
function processFirstFridayBatch_(){
  if (bgShouldHalt_()) { jobSetActive_(false); return { done:true, reason:'HALTED' }; }
  if (jobGet_(KEY_JOB_TYPE) !== 'first_friday') return { done:true, reason:'NO_JOB' };

  const monthHeader = jobGet_(KEY_JOB_MONTH);
  const baseTpl = jobGet_(KEY_JOB_TEXT) || getFirstFridayTemplate_();
  const startFrom = Number(jobGet_(KEY_JOB_NEXT_ID) || 0) || 0;

  const { data, header } = getDataAndHeader_();
  const iId = colIndex_(header, 'id');
  const iNaam = colIndex_(header, 'Naam');
  const iTel = colIndex_(header, 'Telefoon');
  const iSMS = colIndex_(header, 'sms');
  const iMonth= colIndex_(header, monthHeader);

  const t0 = Date.now();
  const checkpointN = checkpointEveryN_();
  const maxPerTick = getMaxPerTick_();

  let sent=0, skipped=0, lastIdUsed=startFrom;

  for (let r=1; r<data.length; r++){
    if (bgShouldHalt_()) break;

    const rowId = Number(data[r][iId] || 0) || 0;
    if (startFrom && rowId < startFrom) { skipped++; continue; }
    if (!bool_(data[r][iSMS])) { skipped++; continue; }
    const paidRaw = data[r][iMonth];
    const paid = (paidRaw === 0 ? '0' : String(paidRaw || '').trim());
    if (paid !== '') { skipped++; continue; } // يبقى الشرط كما هو


    const phone = normalizePhone_(data[r][iTel]);
    if (!phone) { skipped++; continue; }

    const msg = fillTemplate_(baseTpl, { Naam: data[r][iNaam], month: monthHeader });

    try{
      const status = sendViaBulkGate_(phone, msg, 'first-friday');
      logSend_('FirstFriday', monthHeader, data[r][iId], data[r][iNaam], phone, msg, status);
      sent++; lastIdUsed = rowId;

      if (sent % checkpointN === 0) jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
      if (maxPerTick && sent >= maxPerTick){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId: lastIdUsed + 1 };
      }
      if (willTimeoutSoon_(t0)){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'TIME_BUDGET', nextId: lastIdUsed + 1 };
      }
      batchControl_(sent);

    }catch(e){
      const em = String(e.message||'');
      if (em.indexOf('REMOTE_RATE_LIMIT')>=0 || em.indexOf('REMOTE_HOURLY')>=0 || em.indexOf('HOURLY')>=0){
        const nextId = lastIdUsed ? (lastIdUsed+1) : (rowId+1);
        jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
        return { done:false, reason:'HIT_QUOTA', nextId };
      }
      if (em.indexOf('HALTED')>=0) break;
      logSend_('FirstFriday', monthHeader, data[r][iId], data[r][iNaam], phone, msg, 'ERR '+em);
    }
  }

// تحقق الباقي
    let remaining=false;
    for (let r=1; r<data.length; r++){
      const rowId = Number(data[r][iId] || 0) || 0;
      if (lastIdUsed && rowId <= lastIdUsed) continue;
      if (!bool_(data[r][iSMS])) continue;

      // 👈 اعتبر 0 (رقم/نص) قيمة غير فارغة = مدفوع
      const paidRaw = data[r][iMonth];
      const paid = (paidRaw === 0 ? '0' : String(paidRaw || '').trim());

      const phone = normalizePhone_(data[r][iTel]);
      if (paid === '' && phone){ remaining=true; break; }
    }
    if (remaining){
      const nextId = lastIdUsed ? (lastIdUsed+1) : startFrom;
      jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
      return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId };
    } else {
      return { done:true, reason:'FINISHED' };
    }

}

// 3) Mid Month
function processMidMonthBatch_(){
  if (bgShouldHalt_()) { jobSetActive_(false); return { done:true, reason:'HALTED' }; }
  if (jobGet_(KEY_JOB_TYPE) !== 'mid_month') return { done:true, reason:'NO_JOB' };

  const monthHeader = jobGet_(KEY_JOB_MONTH);
  const baseTpl = jobGet_(KEY_JOB_TEXT) || getMidMonthTemplate_();
  const startFrom = Number(jobGet_(KEY_JOB_NEXT_ID) || 0) || 0;

  const { data, header } = getDataAndHeader_();
  const iId = colIndex_(header, 'id');
  const iNaam = colIndex_(header, 'Naam');
  const iTel = colIndex_(header, 'Telefoon');
  const iSMS = colIndex_(header, 'sms');
  const iMonth= colIndex_(header, monthHeader);

  const t0 = Date.now();
  const checkpointN = checkpointEveryN_();
  const maxPerTick = getMaxPerTick_();

  let sent=0, skipped=0, lastIdUsed=startFrom;

  for (let r=1; r<data.length; r++){
    if (bgShouldHalt_()) break;

    const rowId = Number(data[r][iId] || 0) || 0;
    if (startFrom && rowId < startFrom) { skipped++; continue; }
    if (!bool_(data[r][iSMS])) { skipped++; continue; }

    const cell = String(data[r][iMonth] || '').trim().toLowerCase();
    if (cell !== 'x') { skipped++; continue; } // فقط X

    const phone = normalizePhone_(data[r][iTel]);
    if (!phone) { skipped++; continue; }

    const msg = fillTemplate_(baseTpl, { Naam: data[r][iNaam], month: monthHeader });

    try{
      const status = sendViaBulkGate_(phone, msg, 'mid-month');
      logSend_('MidMonth', monthHeader, data[r][iId], data[r][iNaam], phone, msg, status);
      sent++; lastIdUsed = rowId;

      if (sent % checkpointN === 0) jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
      if (maxPerTick && sent >= maxPerTick){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId: lastIdUsed + 1 };
      }
      if (willTimeoutSoon_(t0)){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'TIME_BUDGET', nextId: lastIdUsed + 1 };
      }
      batchControl_(sent);

    }catch(e){
      const em = String(e.message||'');
      if (em.indexOf('REMOTE_RATE_LIMIT')>=0 || em.indexOf('REMOTE_HOURLY')>=0 || em.indexOf('HOURLY')>=0){
        const nextId = lastIdUsed ? (lastIdUsed+1) : (rowId+1);
        jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
        return { done:false, reason:'HIT_QUOTA', nextId };
      }
      if (em.indexOf('HALTED')>=0) break;
      logSend_('MidMonth', monthHeader, data[r][iId], data[r][iNaam], phone, msg, 'ERR '+em);
    }
  }

  // تحقق الباقي
  let remaining=false;
  for (let r=1; r<data.length; r++){
    const rowId = Number(data[r][iId] || 0) || 0;
    if (lastIdUsed && rowId <= lastIdUsed) continue;
    if (!bool_(data[r][iSMS])) continue;
    const cell = String(data[r][iMonth] || '').trim().toLowerCase();
    const phone = normalizePhone_(data[r][iTel]);
    if (cell === 'x' && phone){ remaining=true; break; }
  }
  if (remaining){
    const nextId = lastIdUsed ? (lastIdUsed+1) : startFrom;
    jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
    return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId };
  } else {
    return { done:true, reason:'FINISHED' };
  }
}

// 4) By Month + Condition
function processByMonthCondBatch_(){
  if (bgShouldHalt_()) { jobSetActive_(false); return { done:true, reason:'HALTED' }; }
  if (jobGet_(KEY_JOB_TYPE) !== 'by_month_cond') return { done:true, reason:'NO_JOB' };

  const monthHeader = jobGet_(KEY_JOB_MONTH);
  const cond = jobGet_(KEY_JOB_COND); // 'empty' | 'x' | 'zero'
  const userText = jobGet_(KEY_JOB_TEXT) || '';
  const startFrom = Number(jobGet_(KEY_JOB_NEXT_ID) || 0) || 0;

  const { data, header } = getDataAndHeader_();
  const iId   = colIndex_(header, 'id');
  const iNaam = colIndex_(header, 'Naam');
  const iTel  = colIndex_(header, 'Telefoon');
  const iSMS  = colIndex_(header, 'sms');
  const iMonth= colIndex_(header, monthHeader);

  const wantEmpty = (cond === 'empty');
  const wantX     = (cond === 'x');
  const wantZero  = (cond === 'zero');

  const t0 = Date.now();
  const checkpointN = checkpointEveryN_();
  const maxPerTick = getMaxPerTick_();

  let sent=0, skipped=0, errors=0, lastIdUsed=startFrom;

  for (let r=1; r<data.length; r++){
    if (bgShouldHalt_()) break;

    const rowId = Number(data[r][iId] || 0) || 0;
    if (startFrom && rowId < startFrom) { skipped++; continue; }
    if (!bool_(data[r][iSMS])) { skipped++; continue; }

    // ⚠️ إصلاح: لا تستخدم (|| '') قبل التحويل لأن 0 يعتبر falsy
    const cellRaw = data[r][iMonth];
    const cell = (cellRaw === 0 ? '0' : String(cellRaw || '').trim());

    const match = (wantEmpty && cell==='') ||
                  (wantX && cell.toLowerCase()==='x') ||
                  (wantZero && cell === '0');

    if (!match) { skipped++; continue; }

    const phone = normalizePhone_(data[r][iTel]);
    if (!phone) { skipped++; continue; }

    const msg = fillTemplate_(String(userText||''), { Naam: data[r][iNaam], month: monthHeader });

    try{
      const status = sendViaBulkGate_(phone, msg, ('by-' + cond + '-' + monthHeader));
      logSend_('ByMonthCondition', monthHeader, data[r][iId], data[r][iNaam], phone, msg, status);
      sent++; lastIdUsed = rowId;

      if (sent % checkpointN === 0) jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
      if (maxPerTick && sent >= maxPerTick){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId: lastIdUsed + 1 };
      }
      if (willTimeoutSoon_(t0)){
        jobSet_({ [KEY_JOB_NEXT_ID]: lastIdUsed + 1 });
        return { done:false, reason:'TIME_BUDGET', nextId: lastIdUsed + 1 };
      }
      batchControl_(sent);

    }catch(e){
      const em = String(e.message||'');
      if (em.indexOf('REMOTE_RATE_LIMIT')>=0 || em.indexOf('REMOTE_HOURLY')>=0 || em.indexOf('HOURLY')>=0){
        const nextId = lastIdUsed ? (lastIdUsed+1) : (rowId+1);
        jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
        return { done:false, reason:'HIT_QUOTA', nextId };
      }
      if (em.indexOf('HALTED')>=0) break;
      errors++;
      logSend_('ByMonthCondition', monthHeader, data[r][iId], data[r][iNaam], phone, msg, 'ERR '+em);
    }
  }

  // تحقق الباقي مع دعم zero
  let remaining=false;
  for (let r=1; r<data.length; r++){
    const rowId = Number(data[r][iId] || 0) || 0;
    if (lastIdUsed && rowId <= lastIdUsed) continue;
    if (!bool_(data[r][iSMS])) continue;

    const cellRaw = data[r][iMonth];
    const cell = (cellRaw === 0 ? '0' : String(cellRaw || '').trim());
    const phone = normalizePhone_(data[r][iTel]);

    const match = (wantEmpty && cell==='') ||
                  (wantX && cell.toLowerCase()==='x') ||
                  (wantZero && cell === '0');

    if (match && phone){ remaining=true; break; }
  }
  if (remaining){
    const nextId = lastIdUsed ? (lastIdUsed+1) : startFrom;
    jobSet_({ [KEY_JOB_NEXT_ID]: nextId });
    return { done:false, reason:'BATCH_FINISHED_REMAINING', nextId };
  } else {
    return { done:true, reason:'FINISHED' };
  }
}


/** =========[ محرّك الاستئناف التلقائي العام ]========= **/
function runJobTick(){
  try{
    if (!jobIsActive_()) return;
    if (bgShouldHalt_()){
      jobSetActive_(false);
      safeToast_('إيقاف يدوي مفعّل.');
      return;
    }
    const type = jobGet_(KEY_JOB_TYPE);
    let res = {done:true, reason:'NO_JOB'};

    if (type === 'send_all')        res = processSendAllBatch_();
    else if (type === 'first_friday') res = processFirstFridayBatch_();
    else if (type === 'mid_month')    res = processMidMonthBatch_();
    else if (type === 'by_month_cond')res = processByMonthCondBatch_();

    if (res.done){
      safeToast_('انتهت المهمة: ' + (res.reason||'FINISHED'));
      jobClear_();
      return;
    }

    // لم تنتهِ: سنعيد الجدولة
    const longPause = getResumeDelayMinutes_();
    const shortPause = getRetryShortMinutes_();
    const minutes = (res.reason==='HIT_QUOTA') ? longPause : shortPause;

    if (res.reason === 'HIT_QUOTA'){
      safeToast_('تم بلوغ حد الإرسال (المزوّد/الساعة). سيتم الاستئناف تلقائيًا لاحقًا.');
    } else if (res.reason === 'BATCH_FINISHED_REMAINING'){
      safeToast_('تم إرسال دفعة وسيتم استكمال الدفعات تلقائيًا بعد قليل.');
    } else if (res.reason === 'TIME_BUDGET'){
      safeToast_('انتهى وقت السكريبت، سنستأنف تلقائيًا.');
    }
    scheduleOneOff_('runJobTick', minutes);

  } catch(e){
const em = (e && e.message) ? e.message : String(e);
  Logger.log('runJobTick ERROR: ' + em);
  Logger.log(e && e.stack ? e.stack : '');

  safeToast_('خطأ في runJobTick: ' + em);
  scheduleOneOff_('runJobTick', 1);
  }
}
// التوافق مع اسم سابق
function runSendAllJobTick(){ runJobTick(); }

/** =========[ الدوال المجدولة (القوالب الشهرية) ]========= **/
function sendFirstFridayReminders(){
  const today = new Date();
  const monthHeader = monthHeaderForDate_(today);
  if (!monthHeader) return;
  jobSetActive_(true);
  jobSet_({
    [KEY_JOB_TYPE]: 'first_friday',
    [KEY_JOB_TEXT]: getFirstFridayTemplate_(),
    [KEY_JOB_NEXT_ID]: 0,
    [KEY_JOB_TAG]: 'first-friday',
    [KEY_JOB_MONTH]: monthHeader
  });
  scheduleOneOff_('runJobTick', 0.2);
  safeToast_('تم بدء إرسال أول جمعة (Transactional + استئناف تلقائي).');
}
function sendMidMonthLateNotice(){
  const today = new Date();
  const monthHeader = monthHeaderForDate_(today);
  if (!monthHeader) return;
  jobSetActive_(true);
  jobSet_({
    [KEY_JOB_TYPE]: 'mid_month',
    [KEY_JOB_TEXT]: getMidMonthTemplate_(),
    [KEY_JOB_NEXT_ID]: 0,
    [KEY_JOB_TAG]: 'mid-month',
    [KEY_JOB_MONTH]: monthHeader
  });
  scheduleOneOff_('runJobTick', 0.2);
  safeToast_('تم بدء إرسال منتصف الشهر (Transactional + استئناف تلقائي).');
}

/** =========[ إدارة التريغرات العامة ]========= **/
function remindersDailyDispatcher(){
  const now = new Date();
  if (isFirstFriday_(now)) sendFirstFridayReminders();
  if (isMidMonth_(now))    sendMidMonthLateNotice();
}
function enableRemindersTrigger(){
  // أزل أي تريغرات قديمة لنفس المعالج
  ScriptApp.getProjectTriggers().forEach(t=>{
    if (t.getHandlerFunction() === 'remindersDailyDispatcher') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('remindersDailyDispatcher')
    .timeBased().atHour(TRIGGER_HOUR).nearMinute(TRIGGER_MINUTE).everyDays(1).create();
  safeToast_('تم تفعيل التريغر اليومي للتذكيرات.');
}
function disableRemindersTrigger(){
  let removed=0;
  ScriptApp.getProjectTriggers().forEach(t=>{
    if (t.getHandlerFunction() === 'remindersDailyDispatcher'){
      ScriptApp.deleteTrigger(t); removed++;
    }
  });
  safeToast_(removed ? 'تم إيقاف التريغر.' : 'لا يوجد تريغر مفعّل.');
}

/** =========[ حفظ/جلب آخر نص للواجهات ]========= **/
function setLastText(key, text){ getDocProps_().setProperty(String(key), String(text||'')); }
function getLastText(key){ return getDocProps_().getProperty(String(key)) || ''; }

/** =========[ واجهات: إرسال ]========= **/
function showSendAllSidebar(){
  const html = HtmlService.createHtmlOutput(buildSendAllHTML_()).setTitle('إرسال جماعي (send_all=TRUE)');
  SpreadsheetApp.getUi().showSidebar(html);
}
function runSendAllWithMessage(userText, startId){
  setLastText(KEY_LAST_SENDALL, userText);
  bgSetHalt_(false);
  jobSetActive_(true);
  jobSet_({
    [KEY_JOB_TYPE]: 'send_all',
    [KEY_JOB_TEXT]: String(userText || ''),
    [KEY_JOB_NEXT_ID]: Number(startId || 0) || 0,
    [KEY_JOB_TAG]: 'send_all_auto',
    [KEY_JOB_MONTH]: monthHeaderForDate_(new Date()) || ''
  });
  if (jobGet_(KEY_JOB_TRIGGER_SET) !== '1'){
    scheduleOneOff_('runJobTick', 0.2);
    jobSet_({ [KEY_JOB_TRIGGER_SET]: '1' });
  }
  return { started:true, message:'تم بدء مهمة إرسال جماعي آلي (Transactional). سيتم الاستكمال تلقائياً حتى الإنهاء.' };
}
function buildSendAllHTML_(){
  return `
  <div style="font-family:system-ui,Segoe UI,Arial; padding:12px;">
    <h2 style="margin:0 0 8px;">إرسال جماعي (send_all=TRUE)</h2>
    <p style="margin:0 0 8px;">اكتب <b>النص الكامل</b> للرسالة بالهولندية. يمكنك إدراج المتغيّرات <code>{{Naam}}</code> و <code>{{month}}</code>. سيتم إرسال الرسالة كما هي بدون أي إضافات.</p>
    <label>Start vanaf ID:</label>
    <input id="startId" type="number" min="0" placeholder="مثال: 101" style="width:100%;margin:4px 0 10px;" />
    <div style="margin-bottom:6px;">
      <button id="insName">إدراج {{Naam}}</button>
      <button id="insMonth">إدراج {{month}}</button>
      <button id="loadLast">تحميل آخر نص</button>
      <button id="clear">مسح</button>
      <button id="btnStop" style="float:right;">⛔ إيقاف الإرسال</button>
    </div>
    <textarea id="msg" style="width:100%;height:170px;" placeholder="Voorbeeld: Beste familie van student {{Naam}}, graag herinneren wij aan de bijdrage van {{month}}."></textarea>
    <div style="margin-top:6px;font-size:12px;">
      <span id="stats">0 حرف — 1 رسالة (تقديري، ASCII)</span>
    </div>
    <button id="sendBtn" style="margin-top:10px;padding:8px 14px;">إرسال الآن</button>
    <div id="res" style="margin-top:10px; font-size:13px;"></div>
    <script>
      const KEY = '${KEY_LAST_SENDALL}';
      const msgEl = document.getElementById('msg');
      const startEl = document.getElementById('startId');
      const statsEl = document.getElementById('stats');
      const resEl = document.getElementById('res');
      const btnSend = document.getElementById('sendBtn');

      function asciiSanitize(t){
        if(!t) return '';
        try{ t = t.normalize('NFD').replace(/[\\u0300-\\u036f]/g,''); }catch(e){}
        const map = {'’':"'",'‘':"'",'“':'"','”':'"','—':'-','–':'-','…':'...','€':'EUR','£':'GBP','•':'-'};
        t = t.replace(/[’‘“”—–…€£•]/g, m=>map[m]||'');
        t = t.replace(/[^\\x00-\\x7F]/g,'');
        t = t.replace(/\\s+/g,' ').trim();
        return t;
      }
      function segments(t){
        const s = asciiSanitize(t);
        const single=160, multi=153;
        return s.length<=single ? 1 : Math.ceil(s.length/multi);
      }
      function updateStats(){
        const t = msgEl.value||'';
        const s = asciiSanitize(t);
        statsEl.textContent = s.length + ' حرف — ' + segments(t) + ' رسالة (تقديري، ASCII)';
      }
      function insertAtCursor(str){
        const el = msgEl;
        const start=el.selectionStart, end=el.selectionEnd;
        el.value = el.value.substring(0,start) + str + el.value.substring(end);
        el.selectionStart = el.selectionEnd = start + str.length;
        el.focus(); updateStats();
      }

      google.script.run.withSuccessHandler((val)=>{ if(val){ msgEl.value = val; updateStats(); } }).getLastText(KEY);
      document.getElementById('insName').onclick = ()=>insertAtCursor('{{Naam}}');
      document.getElementById('insMonth').onclick= ()=>insertAtCursor('{{month}}');
      document.getElementById('loadLast').onclick = ()=>google.script.run.withSuccessHandler((val)=>{ msgEl.value = val||''; updateStats(); }).getLastText(KEY);
      document.getElementById('clear').onclick= ()=>{ msgEl.value=''; updateStats(); };
      document.getElementById('btnStop').onclick = ()=>{ google.script.run.withSuccessHandler(()=>{ resEl.textContent = 'تم تفعيل الإيقاف الفوري.'; })
        .withFailureHandler(e=>{ resEl.textContent='خطأ: '+e.message; }).stopAllSending(); };

      msgEl.addEventListener('input', updateStats); updateStats();

      btnSend.onclick = ()=>{
        btnSend.disabled = true;
        resEl.textContent = 'جارٍ بدء مهمة الإرسال التلقائي...';
        const startId = Number(startEl.value||0)||0;
        google.script.run.withSuccessHandler((r)=>{
          resEl.textContent = (r && r.message) ? r.message : 'تم البدء. راقب ورقة SMS_Log للتقدم.';
          btnSend.disabled = false;
        }).withFailureHandler((e)=>{
          resEl.textContent = 'خطأ: '+e.message; btnSend.disabled=false;
        }).runSendAllWithMessage(msgEl.value||'', startId);
      };
    </script>
  </div>`;
}

function showMonthConditionSidebar(){
  const html = HtmlService.createHtmlOutput(buildMonthConditionHTML_()).setTitle('إرسال حسب شهر/شرط');
  SpreadsheetApp.getUi().showSidebar(html);
}
function runSendByMonthAndCondition(monthHeader, condition, userText, startId){
  setLastText(KEY_LAST_BYMONTH, userText);
  if (!MONTH_HEADERS.includes(monthHeader)) throw new Error('اسم الشهر غير صحيح أو غير موجود ضمن الأعمدة.');

  // Job مع الاستئناف التلقائي
  bgSetHalt_(false);
  jobSetActive_(true);
  jobSet_({
    [KEY_JOB_TYPE]: 'by_month_cond',
    [KEY_JOB_TEXT]: String(userText || ''),
    [KEY_JOB_NEXT_ID]: Number(startId || 0) || 0,
    [KEY_JOB_TAG]: ('by-' + condition + '-' + monthHeader),
    [KEY_JOB_MONTH]: monthHeader,
    [KEY_JOB_COND]: condition
  });

  scheduleOneOff_('runJobTick', 0.2);
  return { sent: 0, skipped: 0, errors: 0, halted: false };
}
function buildMonthConditionHTML_(){
  const months = MONTH_HEADERS.map(m=>`<option value="${m}">${m}</option>`).join('');
  return `
  <div style="font-family:system-ui,Segoe UI,Arial; padding:12px;">
    <h2 style="margin:0 0 8px;">إرسال حسب شهر/شرط</h2>
    <label>الشهر:</label>
    <select id="month" style="width:100%;margin:4px 0 8px;"> ${months} </select>

    <label>الشرط:</label>
    <select id="cond" style="width:100%;margin:4px 0 8px;">
  <option value="empty">الخانة فارغة (لم يدفع)</option>
  <option value="x">الخانة تحتوي X (متأخر)</option>
  <option value="zero">الخانة تحتوي 0 (صفر)</option>
</select>


    <label>Start vanaf ID:</label>
    <input id="startId" type="number" min="0" placeholder="مثال: 201" style="width:100%;margin:4px 0 8px;" />

    <p style="margin:8px 0;">اكتب <b>النص الكامل</b> للرسالة بالهولندية. يدعم المتغيّرات <code>{{Naam}}</code> و <code>{{month}}</code>.</p>
    <div style="margin-bottom:6px;">
      <button id="insName">إدراج {{Naam}}</button>
      <button id="insMonth">إدراج {{month}}</button>
      <button id="loadLast">تحميل آخر نص</button>
      <button id="clear">مسح</button>
      <button id="btnStop" style="float:right;">⛔ إيقاف الإرسال</button>
    </div>

    <textarea id="msg" style="width:100%;height:170px;" placeholder="Voorbeeld: Beste familie van student {{Naam}}, betaling voor {{month}} is vertraagd."></textarea>
    <div style="margin-top:6px;font-size:12px;">
      <span id="stats">0 حرف — 1 رسالة (تقديري، ASCII)</span>
    </div>
    <button id="sendBtn" style="margin-top:10px;padding:8px 14px;">إرسال الآن</button>
    <div id="res" style="margin-top:10px; font-size:13px;"></div>

    <script>
      const KEY = '${KEY_LAST_BYMONTH}';
      const msgEl = document.getElementById('msg');
      const statsEl = document.getElementById('stats');
      const resEl = document.getElementById('res');
      const btnSend = document.getElementById('sendBtn');
      const monthEl = document.getElementById('month');
      const condEl = document.getElementById('cond');
      const startEl = document.getElementById('startId');

      function asciiSanitize(t){
        if(!t) return '';
        try{ t = t.normalize('NFD').replace(/[\\u0300-\\u036f]/g,''); }catch(e){}
        const map = {'’':"'",'‘':"'",'“':'"','”':'"','—':'-','–':'-','…':'...','€':'EUR','£':'GBP','•':'-'};
        t = t.replace(/[’‘“”—–…€£•]/g, m=>map[m]||'');
        t = t.replace(/[^\\x00-\\x7F]/g,'');
        t = t.replace(/\\s+/g,' ').trim();
        return t;
      }
      function segments(t){
        const s = asciiSanitize(t);
        const single=160, multi=153;
        return s.length<=single ? 1 : Math.ceil(s.length/multi);
      }
      function updateStats(){
        const t = msgEl.value||'';
        const s = asciiSanitize(t);
        statsEl.textContent = s.length + ' حرف — ' + segments(t) + ' رسالة (تقديري، ASCII)';
      }
      function insertAtCursor(str){
        const el = msgEl;
        const start=el.selectionStart, end=el.selectionEnd;
        el.value = el.value.substring(0,start) + str + el.value.substring(end);
        el.selectionStart = el.selectionEnd = start + str.length;
        el.focus(); updateStats();
      }

      google.script.run.withSuccessHandler((val)=>{ if(val){ msgEl.value = val; updateStats(); } }).getLastText(KEY);
      document.getElementById('insName').onclick = ()=>insertAtCursor('{{Naam}}');
      document.getElementById('insMonth').onclick= ()=>insertAtCursor('{{month}}');
      document.getElementById('loadLast').onclick = ()=>google.script.run.withSuccessHandler((val)=>{ msgEl.value = val||''; updateStats(); }).getLastText(KEY);
      document.getElementById('clear').onclick= ()=>{ msgEl.value=''; updateStats(); };
      document.getElementById('btnStop').onclick = ()=>{ google.script.run.withSuccessHandler(()=>{ resEl.textContent = 'تم تفعيل الإيقاف الفوري.'; })
        .withFailureHandler(e=>{ resEl.textContent='خطأ: '+e.message; }).stopAllSending(); };

      msgEl.addEventListener('input', updateStats); updateStats();

      btnSend.onclick = ()=>{
        btnSend.disabled = true;
        resEl.textContent = 'جارٍ إطلاق المهمة… ستستأنف تلقائيًا إن حدث لمت.';
        const month = monthEl.value;
        const cond = condEl.value;
        const text = msgEl.value||'';
        const startId = Number(startEl.value||0)||0;
        google.script.run.withSuccessHandler((r)=>{
          resEl.textContent = 'تم الإطلاق. راقب ورقة SMS_Log والتقدم سيتم تلقائيًا مع الاستئناف.';
          btnSend.disabled = false;
        }).withFailureHandler((e)=>{
          resEl.textContent = 'خطأ: '+e.message; btnSend.disabled=false;
        }).runSendByMonthAndCondition(month, cond, text, startId);
      };
    </script>
  </div>`;
}

/** =========[ حالة/استئناف ]========= **/
function showJobStatus(){
  const p = getProps_();
  const state = {
    active: p.getProperty(KEY_JOB_ACTIVE),
    type:   p.getProperty(KEY_JOB_TYPE),
    month:  p.getProperty(KEY_JOB_MONTH),
    cond:   p.getProperty(KEY_JOB_COND),
    nextId: p.getProperty(KEY_JOB_NEXT_ID),
    tag:    p.getProperty(KEY_JOB_TAG)
  };
  const msg = `Active:${state.active||'0'} | ${state.type||'-'} | month:${state.month||'-'} | cond:${state.cond||'-'} | nextId:${state.nextId||'0'}`;
  safeToast_(msg);
  Logger.log(JSON.stringify(state,null,2));
}

// استئناف يدوي فوري
function forceResumeNow(){
  if (!jobIsActive_()){ safeToast_('لا توجد مهمة نشطة الآن.'); return; }
  scheduleOneOff_('runJobTick', 0.2);
  safeToast_('تمت جدولة الاستئناف الآن.');
}

/** =========[ Toast آمن ]========= **/
function safeToast_(msg) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    if (ss) ss.toast(String(msg || ''));
  } catch (e) {
    console.log('TOAST:', msg);
  }
}
/** =========[ قائمة الشيت ]========= **/
function onOpen(){
  const ui = SpreadsheetApp.getUi();
  ui.createMenu('📲 SMS BulkGate')
    .addItem('تفعيل التريغر اليومي','enableRemindersTrigger')
    .addItem('إيقاف التريغر','disableRemindersTrigger')
    .addSeparator()
    .addItem('تشغيل الآن: أول جمعة','sendFirstFridayReminders')
    .addItem('تشغيل الآن: منتصف الشهر','sendMidMonthLateNotice')
    .addSeparator()
    .addItem('واجهة: إرسال جماعي (send_all)','showSendAllSidebar')
    .addItem('واجهة: إرسال حسب شهر/شرط','showMonthConditionSidebar')
    .addSeparator()
    .addItem('⛔ إيقاف الإرسال الآن','stopAllSending')
    .addItem('▶️ السماح بالإرسال','resumeSending')
    .addItem('📊 عرض حالة المهمة','showJobStatus')
    .addItem('⏩ استئناف الآن','forceResumeNow')
    .addToUi();
}
