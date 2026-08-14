<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Surveys\Enums\CommentMode;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\VersionSettings;
use Illuminate\Foundation\Http\FormRequest;

final class VersionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las reglas salen de VersionSettings. Escribirlas aqui otra vez seria
     * tener dos verdades sobre la misma forma.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return VersionSettings::rules();
    }

    public function settings(): VersionSettings
    {
        return new VersionSettings(
            identityMode: IdentityMode::from((string) $this->string('identity_mode')),
            commentMode: CommentMode::from((string) $this->string('comment_mode')),
            allowBack: $this->boolean('allow_back'),
            inactivitySeconds: $this->integer('inactivity_seconds'),
            helpEnabled: $this->boolean('help_enabled'),
            introduction: $this->string('introduction')->toString() ?: null,
            thankYou: $this->string('thank_you')->toString() ?: null,
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identity_mode' => __('interface.settings.identity_mode'),
            'comment_mode' => __('interface.settings.comment_mode'),
            'inactivity_seconds' => __('interface.settings.inactivity'),
            'introduction' => __('interface.settings.introduction'),
            'thank_you' => __('interface.settings.thank_you'),
        ];
    }
}
