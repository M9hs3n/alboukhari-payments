<?php

namespace App\Http\Controllers;

use App\Models\Campaign;

class CampaignsController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest()->paginate(30);
        return view('campaigns-index', compact('campaigns'));
    }

    public function show(Campaign $campaign)
    {
        $campaign->load('recipients');
        return view('campaigns-show', compact('campaign'));
    }
}
