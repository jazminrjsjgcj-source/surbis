<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Application\Media\MediaPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Las reglas salen de MediaPolicy: escribirlas aqui crearia una
            // segunda verdad sobre que se admite.
            'file' => MediaPolicy::rules(),
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimetypes' => __('interface.media.wrong_type'),
            'file.mimes' => __('interface.media.wrong_type'),
            'file.max' => __('interface.media.too_big'),
        ];
    }
}
