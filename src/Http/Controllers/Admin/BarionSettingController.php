<?php

namespace Weboldalnet\CommerceBarion\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Weboldalnet\CommerceBarion\Services\BarionPaymentService;
use Weboldalnet\CommerceBarion\Services\BarionSettingsService;

class BarionSettingController extends Controller
{
    public function index()
    {
        // FIGYELEM: a változó neve nem lehet $settings – a platform admin layoutja
        // egy globálisan megosztott $settings modellt használ, azt felülírnánk.
        $barionSettings = BarionSettingsService::all();

        // A DB-ben még nem szereplő mezőknél is a tényleges (config/.env) érték látszódjon
        foreach (BarionSettingsService::viewKeys() as $key) {
            if (!array_key_exists($key, $barionSettings)) {
                $barionSettings[$key] = BarionSettingsService::get($key);
            }
        }

        // A finanszírozási források a nézetben tömbként kényelmesebbek
        $barionSettings['funding_sources'] = BarionSettingsService::fundingSources();

        // Titkosított mezők maszkolása
        foreach (BarionSettingsService::encryptedKeys() as $key) {
            if (!empty($barionSettings[$key])) {
                $barionSettings[$key] = '********';
            }
        }

        return view('commerce-barion::admin.settings', compact('barionSettings'));
    }

    public function update(Request $request)
    {
        $data = $request->all();
        $booleanKeys = BarionSettingsService::booleanKeys();
        $encryptedKeys = BarionSettingsService::encryptedKeys();

        foreach ($data as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $type = 'string';

            if (in_array($key, $booleanKeys, true)) {
                $type = 'boolean';
                $value = ($value === 'on' || $value === '1' || $value === true);
            } elseif (in_array($key, $encryptedKeys, true)) {
                $type = 'encrypted';
                // A maszkolt értéket nem mentjük vissza
                if ($value === '********') {
                    continue;
                }
            } elseif (is_array($value)) {
                // Több értékű mező (finanszírozási források) – vesszős listaként tároljuk
                $value = implode(',', array_filter($value, function ($item) {
                    return $item !== '' && $item !== null;
                }));
            }

            BarionSettingsService::save($key, $value, $type);
        }

        // A be nem küldött checkboxok kikapcsoltnak számítanak
        foreach ($booleanKeys as $key) {
            if (!isset($data[$key])) {
                BarionSettingsService::save($key, false, 'boolean');
            }
        }

        // Egyetlen bejelölt forrás sem érkezett: ilyenkor a Barion minden
        // fizetési módot kínáljon, ne maradjon üres a lista.
        if (!isset($data['funding_sources'])) {
            BarionSettingsService::save('funding_sources', 'All');
        }

        return redirect()->back()->with('success', 'Barion beállítások sikeresen mentve.');
    }

    /**
     * Kapcsolat tesztelése a Barion API felé.
     */
    public function testConnection(BarionPaymentService $service)
    {
        try {
            return response()->json($service->testConnection());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba a kapcsolódáskor: ' . $e->getMessage(),
            ]);
        }
    }
}
