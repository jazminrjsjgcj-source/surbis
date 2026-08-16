<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Surveys\Enums\RenderLayout;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Rendering\RenderableSurvey;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Vista previa. RF-AO-BLD-008 · RF-AO-PUB-004 · RNF-AO-PUB-002.
 *
 * Usa EL MISMO RenderableSurvey y EL MISMO componente que el canal real. Si
 * fueran distintos, la vista previa dejaria de predecir lo que vera quien
 * conteste, y entonces no sirve para nada.
 *
 * Lo unico que cambia es que nada se envia: onComplete no guarda.
 */
final class SurveyPreviewController extends Controller
{
    public function __invoke(Request $request, Survey $survey): InertiaResponse
    {
        $this->authorize('view', $survey);

        /*
         * Se previsualiza el BORRADOR si lo hay, y si no la publicada.
         *
         * Quien abre la vista previa desde el constructor quiere ver lo que
         * acaba de escribir, no lo que ya esta publicado. Y si no hay
         * borrador, ver lo publicado es mejor que un error.
         */
        $version = $survey->draft ?? $survey->publishedVersion;

        if ($version === null) {
            return Inertia::render('Admin/Surveys/Preview', [
                'survey' => ['ulid' => $survey->ulid, 'name' => $survey->name],
                'version' => null,
                'rendered' => null,
                'layouts' => RenderLayout::values(),
                'backUrl' => route('admin.surveys.edit', $survey),
            ]);
        }

        /*
         * El modo se elige en la pantalla, no viene del canal.
         *
         * RF-AO-PUB-004 pide simular quiosco, telefono, tableta y widget: una
         * misma encuesta puede aplicarse por varios canales a la vez, y sin
         * selector la vista previa no enseñaria como se ve en un enlace.
         */
        $layout = RenderLayout::tryFrom($request->string('layout')->toString())
            ?? RenderLayout::Stepped;

        return Inertia::render('Admin/Surveys/Preview', [
            'survey' => ['ulid' => $survey->ulid, 'name' => $survey->name],

            'version' => [
                'number' => $version->version_number,
                'isDraft' => $version->id === $survey->draft?->id,
            ],

            'rendered' => (new RenderableSurvey($version, $layout))->toArray(),
            'layout' => $layout->value,
            'layouts' => RenderLayout::values(),

            'previewUrl' => route('admin.surveys.preview', $survey),
            'backUrl' => route('admin.surveys.edit', $survey),
        ]);
    }
}
