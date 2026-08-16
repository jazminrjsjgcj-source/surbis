<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    use HasPublicUlid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'uploaded_by',
        'name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'alt_text',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Lo que esta organizacion puede usar: lo suyo y lo de sistema.
     *
     * RNF-GEN-005 con una excepcion declarada. Sin este scope, cada consulta
     * decidiria por su cuenta si incluye los recursos de sistema, y alguna se
     * olvidaria.
     *
     * @param  Builder<MediaItem>  $query
     * @return Builder<MediaItem>
     */
    public function scopeUsableBy(Builder $query, int $organizationId): Builder
    {
        return $query->where(function (Builder $inner) use ($organizationId): void {
            $inner->where('organization_id', $organizationId)
                ->orWhereNull('organization_id');
        });
    }

    /** @param Builder<MediaItem> $query */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Los recursos de sistema NO se editan ni se borran.
     *
     * Vienen con el producto y son iguales para todas las organizaciones:
     * dejar que una los cambie afectaria a las demas.
     */
    public function isSystem(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * La URL desde la que se ve esta imagen.
     *
     * Los recursos de SISTEMA van en el disco publico y se sirven directos:
     * son del producto, iguales para todas las organizaciones, y no hay nada
     * que proteger.
     *
     * Lo que sube cada organizacion NO. Esas son fotos suyas, y publicarlas
     * en un disco accesible dejaria que cualquiera las viera adivinando la
     * ruta. Se sirven por una ruta que comprueba a que organizacion
     * pertenece quien mira.
     */
    public function url(): string
    {
        if ($this->isSystem()) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return route('media.show', $this->ulid);
    }

    /**
     * El nombre accesible. RNF-GEN-006.
     *
     * Cae al nombre del archivo si no hay texto alternativo: es peor que uno
     * escrito a mano, pero mucho mejor que una imagen anunciada como "imagen".
     */
    public function accessibleName(): string
    {
        return $this->alt_text ?: $this->name;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
