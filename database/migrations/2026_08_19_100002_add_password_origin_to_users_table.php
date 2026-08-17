<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si la contrasena la puso OTRA persona al crear la cuenta.
     *
     * Existe porque cambia quien la conoce. Normalmente solo la sabe su
     * dueño: la invitacion por correo lleva un enlace y la persona la elige
     * en privado.
     *
     * Cuando no hay correo configurado, quien da de alta tiene que ponerla, y
     * entonces la conocen dos. Mientras no se cambie, ese administrador puede
     * entrar como esa persona —y la auditoria registraria sus acciones con el
     * nombre del titular, no con el suyo—.
     *
     * Se marca para que quien administra VEA donde esta ese riesgo. No se
     * bloquea nada: el cambio no es obligatorio. Decision del area usuaria,
     * 19 ago 2026.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('password_set_by_other_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_set_by_other_at');
        });
    }
};
