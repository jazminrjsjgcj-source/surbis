<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Surveys\OpenDraft;
use App\Application\Surveys\UpdateVersionSettings;
use App\Domain\Surveys\Enums\CommentMode;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VersionSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Configuracion de la version. RF-AO-PUB-001.
 *
 * Se edita el BORRADOR, nunca una version publicada. Si no hay borrador, se
 * abre uno: el administrador que entra a cambiar el modo de identidad no
 * tiene por que saber que antes hay que crear una version.
 */
final class VersionSettingsController extends Controller
{
    public function edit(Survey $survey, OpenDraft $open): InertiaResponse
    {
        $this->authorize('update', $survey);

        // Se abre el borrador al entrar, igual que en el constructor: quien
        // viene a configurar no tiene por que saber que antes hay que crear
        // una version.
        $draft = $open->execute($survey);
        $settings = $draft->settings;

        return Inertia::render('Admin/Surveys/Settings', [
            'survey' => ['ulid' => $survey->ulid, 'name' => $survey->name],

            'settings' => [
                'version_number' => $draft->version_number,
                'identity_mode' => $settings->identityMode->value,
                'comment_mode' => $settings->commentMode->value,
                'allow_back' => $settings->allowBack,
                'inactivity_seconds' => $settings->inactivitySeconds,
                'help_enabled' => $settings->helpEnabled,
                'introduction' => $settings->introduction,
                'thank_you' => $settings->thankYou,
            ],

            /*
             * Los modos vienen del servidor en lugar de escribirse en
             * TypeScript. Si React tuviera su propia lista, el dia que se
             * anada un modo la pantalla no lo ofreceria y nadie sabria por
             * que.
             */
            'identityModes' => array_map(
                fn (IdentityMode $mode): string => $mode->value,
                IdentityMode::cases(),
            ),
            'commentModes' => array_map(
                fn (CommentMode $mode): string => $mode->value,
                CommentMode::cases(),
            ),

            'action' => route('admin.surveys.settings.update', $survey),
            'backUrl' => route('admin.surveys.edit', $survey),
            'publishedVersion' => $survey->publishedVersion?->version_number,
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
