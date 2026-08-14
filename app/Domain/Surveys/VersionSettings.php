<?php

declare(strict_types=1);

namespace App\Domain\Surveys;

use App\Domain\Surveys\Enums\CommentMode;
use App\Domain\Surveys\Enums\IdentityMode;
use Illuminate\Validation\Rule;

/**
 * La configuracion de una version, con forma declarada.
 *
 * survey_versions.settings es un jsonb, y un jsonb sin estructura declarada es
 * el sitio donde se acumula lo que nadie valida. Sin esta clase, dentro de
 * tres fases habria versiones con claves distintas segun cuando se crearon y
 * nadie sabria cuales son validas.
 *
 * Aqui las claves se declaran una vez, y de esa declaracion salen tres cosas:
 * los valores por defecto, las reglas de validacion y lo que se guarda. No
 * pueden divergir porque son el mismo sitio.
 *
 * RF-AO-PUB-001.
 */
final class VersionSettings
{
    public const MIN_INACTIVITY_SECONDS = 15;

    public const MAX_INACTIVITY_SECONDS = 900;

    public const DEFAULT_INACTIVITY_SECONDS = 60;

    public function __construct(
        public readonly IdentityMode $identityMode = IdentityMode::Anonymous,
        public readonly CommentMode $commentMode = CommentMode::Optional,
        public readonly bool $allowBack = true,
        public readonly int $inactivitySeconds = self::DEFAULT_INACTIVITY_SECONDS,
        public readonly bool $helpEnabled = false,
        public readonly ?string $introduction = null,
        public readonly ?string $thankYou = null,
    ) {}

    /**
     * El modo por defecto es anonimo, y no es casualidad.
     *
     * Si alguien crea una encuesta y publica sin mirar esta pantalla, lo que
     * ocurre es que no se recogen datos personales. El defecto contrario
     * —pedir identidad salvo que se desactive— convertiria un descuido en una
     * captura de datos que nadie autorizo.
     */
    public static function default(): self
    {
        return new self;
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $default = self::default();

        return new self(
            identityMode: IdentityMode::tryFrom((string) ($data['identity_mode'] ?? '')) ?? $default->identityMode,
            commentMode: CommentMode::tryFrom((string) ($data['comment_mode'] ?? '')) ?? $default->commentMode,
            allowBack: (bool) ($data['allow_back'] ?? $default->allowBack),
            inactivitySeconds: (int) ($data['inactivity_seconds'] ?? $default->inactivitySeconds),
            helpEnabled: (bool) ($data['help_enabled'] ?? $default->helpEnabled),
            introduction: self::text($data['introduction'] ?? null),
            thankYou: self::text($data['thank_you'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identity_mode' => $this->identityMode->value,
            'comment_mode' => $this->commentMode->value,
            'allow_back' => $this->allowBack,
            'inactivity_seconds' => $this->inactivitySeconds,
            'help_enabled' => $this->helpEnabled,
            'introduction' => $this->introduction,
            'thank_you' => $this->thankYou,
        ];
    }

    /**
     * Las reglas salen de la misma clase que los valores por defecto.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'identity_mode' => ['required', Rule::enum(IdentityMode::class)],
            'comment_mode' => ['required', Rule::enum(CommentMode::class)],
            'allow_back' => ['sometimes', 'boolean'],
            'help_enabled' => ['sometimes', 'boolean'],

            /*
             * El minimo no es arbitrario: por debajo de quince segundos, una
             * persona que lee una pregunta antes de contestarla veria la
             * pantalla reiniciarse en la cara. RF-COL-012 pide reiniciar tras
             * la inactividad, no interrumpir a quien esta pensando.
             */
            'inactivity_seconds' => [
                'required',
                'integer',
                'min:'.self::MIN_INACTIVITY_SECONDS,
                'max:'.self::MAX_INACTIVITY_SECONDS,
            ],

            'introduction' => ['nullable', 'string', 'max:2000'],
            'thank_you' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
