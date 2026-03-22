<?php

declare(strict_types=1);

/**
 * Fachada de asignación de mesas: delega el algoritmo en MesaAsignacionAlgorithm
 * y la persistencia/consultas en app/Core/MesaRepository.
 *
 * No genera HTML; las vistas de carga de resultados viven en public/views/partials/mesas/.
 *
 * Despliegue: no reemplazar por la monolítica de _LEGACY_RAW ni por versiones con entidad_id en
 * INSERT partiresul. El respaldo debe llamarse MesaAsignacionService.old.php (sin declarar esta clase).
 * TorneoMesaAsignacionResolver exige la propiedad $repo.
 */

require_once __DIR__ . '/../../app/Core/MesaRepository.php';
require_once __DIR__ . '/MesaAsignacion/MesaAsignacionAlgorithm.php';

class MesaAsignacionService
{
    private MesaRepository $repo;
    private MesaAsignacionAlgorithm $algorithm;

    public function __construct()
    {
        $this->repo = new MesaRepository(DB::pdo());
        $this->algorithm = new MesaAsignacionAlgorithm($this->repo);
    }

    public function generarAsignacionRonda($torneoId, $numRonda, $totalRondas, $estrategiaRonda2 = 'separar')
    {
        return $this->algorithm->generarAsignacionRonda($torneoId, $numRonda, $totalRondas, $estrategiaRonda2);
    }

    public function yaJugaronJuntos(int $torneoId, int $id1, int $id2, int $hastaRonda): bool
    {
        return $this->repo->yaJugaronJuntos($torneoId, $id1, $id2, $hastaRonda);
    }

    public function obtenerUltimaRonda($torneoId)
    {
        return $this->repo->obtenerUltimaRonda((int) $torneoId);
    }

    public function obtenerProximaRonda($torneoId)
    {
        return $this->repo->obtenerProximaRonda((int) $torneoId);
    }

    public function todasLasMesasCompletas($torneoId, $ronda)
    {
        return $this->repo->todasLasMesasCompletas((int) $torneoId, (int) $ronda);
    }

    public function contarMesasIncompletas($torneoId, $ronda)
    {
        return $this->repo->contarMesasIncompletas((int) $torneoId, (int) $ronda);
    }

    public function rondaTieneResultadosEnMesas($torneoId, $ronda): bool
    {
        return $this->repo->rondaTieneResultadosEnMesas((int) $torneoId, (int) $ronda);
    }

    public function eliminarRonda($torneoId, $ronda)
    {
        return $this->repo->eliminarRonda((int) $torneoId, (int) $ronda);
    }
}
