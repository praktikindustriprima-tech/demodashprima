<?php

namespace App\Http\Controllers;

use App\Models\OltTemplate;
use Illuminate\Http\Request;

class OltTemplateController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'host'     => 'required|string|max:255',
            'port'     => 'required|integer',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        OltTemplate::create($data);

        return redirect()->back()->with('success', 'Template saved.');
    }

    public function destroy(OltTemplate $oltTemplate)
    {
        $oltTemplate->delete();
        return redirect()->back()->with('success', 'Template deleted.');
    }

    public function setDefault(OltTemplate $oltTemplate)
    {
        OltTemplate::query()->update(['is_default' => false]);
        $oltTemplate->update(['is_default' => true]);

        return redirect()->back()->with('success', 'Default template updated.');
    }
}
