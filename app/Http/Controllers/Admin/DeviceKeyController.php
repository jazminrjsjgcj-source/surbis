<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Kiosk\ManageStationKey;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Device;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Generar y revocar la clave de una estacion. TASK-005.
 */
final class DeviceKeyController extends Controller
{
    public function generate(Request $request, Device $device, ManageStationKey $keys): RedirectResponse
    {
        $this->authorize('manageKeys', $device);

        /** @var User $user */
        $user = $request->user();

        $clave = $keys->generate($device, $user);

        /*
         * La clave viaja UNA vez, en un flash.
         *
         * Es la unica ocasion en que existe fuera de la tableta: en la base
         * solo queda su hash. Quien recargue la pantalla ya no la vera, y
         * tendra que generar otra.
         */
        return back()->with([
            'status' => __('interface.kiosks.key_generated'),
            'station_key' => $clave,
            'station_key_device' => $device->ulid,
        ]);
    }

    public function revoke(Request $request, Device $device, ManageStationKey $keys): RedirectResponse
    {
        $this->authorize('manageKeys', $device);

        /** @var User $user */
        $user = $request->user();

        $keys->revoke($device, $user);

        return back()->with('status', __('interface.kiosks.key_revoked'));
    }
}
