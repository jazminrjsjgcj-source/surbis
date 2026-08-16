<?php

declare(strict_types=1);

namespace App\Application\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Kiosk\StationKey;
use App\Domain\Organizations\Models\Device;

/**
 * De una clave de estacion a la configuracion completa. RNF-COL-001.
 *
 * TODO se determina en el servidor: organizacion, sucursal, encuesta y
 * deployment. El dispositivo solo aporta su clave; si mandara cualquiera de
 * esos datos, bastaria con cambiarlos para atribuir respuestas a otra
 * oficina.
 */
final class ResolveStation
{
    public function __construct(private readonly StationKey $keys) {}

    /** @throws StationNotReady */
    public function device(string $key): Device
    {
        $device = Device::query()
            ->where('station_key_hash', $this->keys->hash($key))
            ->with(['branch', 'area', 'organization'])
            ->first();

        /*
         * Una clave desconocida y una revocada dan el MISMO error.
         *
         * Distinguirlas diria si esa clave existio alguna vez, y eso es
         * informacion para quien pruebe claves. RNF-COL-004.
         */
        if ($device === null || $device->station_key_revoked_at !== null) {
            throw StationNotReady::unknownDevice();
        }

        if (! $device->isActive()) {
            throw StationNotReady::deviceInactive();
        }

        return $device;
    }

    /**
     * Que encuesta le toca a este dispositivo.
     *
     * Busca UNA sola cosa: el deployment de ESTE dispositivo. Sin herencia
     * por area ni por sucursal.
     *
     * La primera version buscaba de lo mas especifico a lo mas general, con
     * un orden que desempataba cuando habia varios validos. Eso se retiro por
     * tres razones, y la tercera es la que decidio:
     *
     *   el desempate era una regla invisible: quien configuraba no veia por
     *   que ganaba uno u otro;
     *
     *   una respuesta apunta a un deployment, y con herencia todas las
     *   tabletas de una sede compartirian el mismo, obligando a mirar otra
     *   columna para separar metricas;
     *
     *   desactivar UNA tableta no tendria donde guardarse. Habria que
     *   inventar una tabla de exclusiones, que es un deployment por
     *   dispositivo con otro nombre.
     *
     * Configurar cincuenta ventanillas de golpe se resuelve con una
     * operacion en LOTE sobre sus deployments (ActivateBranchKiosks), no
     * cambiando lo que significa un deployment.
     *
     * @throws StationNotReady
     */
    public function deployment(Device $device): Deployment
    {
        $deployment = Deployment::query()
            ->where('organization_id', $device->organization_id)
            ->where('channel', DeploymentChannel::Kiosk)
            ->where('scope', DeploymentScope::Device)
            ->where('device_id', $device->id)
            ->whereNull('closed_at')
            ->with(['version.survey', 'device', 'branch', 'area'])
            ->first();

        if ($deployment === null) {
            throw StationNotReady::noDeployment();
        }

        /*
         * "Existe" no es "esta aplicando": uno activo con fecha de inicio
         * manana todavia no recibe respuestas.
         *
         * Se distinguen los dos errores porque lo que hay que hacer es
         * distinto: crear una aplicacion, o esperar.
         */
        if (! $deployment->isApplying()) {
            throw StationNotReady::deploymentNotApplying();
        }

        return $deployment;
    }
}
