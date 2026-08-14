<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Surveys\OpenDraft;
use App\Application\Surveys\UpdateVersionSettings;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VersionSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configuracion de la version. RF-AO-PUB-001.
 *
 * Se edita el BORRADOR, nunca una version publicada. Si no hay borrador, se
 * abre uno: el administrador que entra a cambiar el modo de identidad no
 * tiene por que saber que antes hay que crear una version.
 */
final class VersionSettingsController extends Controller
{
    public function edit(Survey $survey, OpenDraft $open): View
    {
        $this->authorize('update', $survey);

        $draft = $open->execute($survey);

        return view('admin.surveys.settings', [
            'survey' => $survey,
            'version' => $draft,
            'settings' => $draft->settings,
        ]);
    }

    public function update(
        VersionSettingsRequest $request,
        Survey $survey,
        OpenDraft $open,
        UpdateVersionSettings $update,
    ): RedirectResponse {
        $this->authorize('update', $survey);

        $update->execute($open->execute($survey), $request->settings());

        return back()->with('status', __('interface.settings.saved'));
    }
}
