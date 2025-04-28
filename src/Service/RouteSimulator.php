<?php
namespace App\Service;

use App\Service\Geocoder;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class RouteSimulator
{
    private $lockDir;

    public function __construct(
        private readonly Geocoder $geocoder
    ) {
        // Define a directory for simulation lock files
        $this->lockDir = sys_get_temp_dir() . '/route_simulator_locks';
        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0755, true);
        }
    }

    /**
     * Check if a simulation is running for the given transportId
     */
    private function isSimulationRunning(int $transportId): bool
    {
        $lockFile = $this->getLockFile($transportId);
        return file_exists($lockFile);
    }

    /**
     * Create a lock file for the simulation
     */
    private function acquireLock(int $transportId): void
    {
        $lockFile = $this->getLockFile($transportId);
        file_put_contents($lockFile, getmypid());
    }

    /**
     * Remove the lock file for the simulation
     */
    private function releaseLock(int $transportId): void
    {
        $lockFile = $this->getLockFile($transportId);
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Get the lock file path for a transportId
     */
    private function getLockFile(int $transportId): string
    {
        return $this->lockDir . '/simulation_' . $transportId . '.lock';
    }

    public function simulate(
        HubInterface $hub,
        int $transportId,
        string $departAddress,
        string $arriveeAddress,
        float $totalDistance,
        array $waypoints = [],
        int $steps = 6,
        int $delay = 1,
        ?callable $onComplete = null
    ): void {
        // Check if a simulation is already running
        if ($this->isSimulationRunning($transportId)) {
            throw new \RuntimeException('A simulation is already running for transport ID ' . $transportId);
        }

        // Acquire lock for this simulation
        $this->acquireLock($transportId);

        try {
            $departCoords = $this->geocoder->geocode($departAddress);
            $arriveeCoords = $this->geocoder->geocode($arriveeAddress);

            if (!$departCoords || !$arriveeCoords) {
                throw new \InvalidArgumentException('Invalid coordinates for addresses');
            }

            // If no waypoints provided, create a direct route
            if (empty($waypoints)) {
                $waypoints = $this->generateWaypoints($departCoords, $arriveeCoords, $steps);
            }

            $totalPoints = count($waypoints);
            $updateInterval = max(1, (int)($totalPoints / 10));

            for ($i = 0; $i < $totalPoints; $i++) {
                if ($i % $updateInterval === 0 || $i === $totalPoints - 1) {
                    $progress = $i / ($totalPoints - 1);
                    $position = [
                        'latitude' => $waypoints[$i]['lat'],
                        'longitude' => $waypoints[$i]['lon'],
                        'distanceCovered' => $totalDistance * $progress,
                        'progress' => $progress * 100,
                        'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                        'isComplete' => $i === $totalPoints - 1 // Flag to indicate completion
                    ];

                    $update = new Update(
                        "tracking/transport/{$transportId}",
                        json_encode($position)
                    );
                    
                    $hub->publish($update);
                    
                    usleep($delay * 1000000);
                }
            }

            if ($onComplete) {
                $onComplete();
            }
        } finally {
            // Always release the lock when done or on error
            $this->releaseLock($transportId);
        }
    }

    private function generateWaypoints(array $start, array $end, int $count): array
    {
        $waypoints = [];
        for ($i = 0; $i <= $count; $i++) {
            $progress = $i / $count;
            $waypoints[] = [
                'lat' => $start['lat'] + ($end['lat'] - $start['lat']) * $progress,
                'lon' => $start['lon'] + ($end['lon'] - $start['lon']) * $progress
            ];
        }
        return $waypoints;
    }

    public function getCurrentPosition(
        int $transportId,
        string $departAddress,
        string $arriveeAddress,
        array $waypoints = []
    ): array {
        $departCoords = $this->geocoder->geocode($departAddress);
        $arriveeCoords = $this->geocoder->geocode($arriveeAddress);

        if (empty($waypoints)) {
            return $this->calculatePosition($departCoords, $arriveeCoords, 100, 0.3);
        }

        $index = (int)(count($waypoints) * 0.3);
        return [
            'latitude' => $waypoints[$index]['lat'],
            'longitude' => $waypoints[$index]['lon'],
            'distanceCovered' => 30,
            'progress' => 30,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }

    private function calculatePosition(
        array $departCoords,
        array $arriveeCoords,
        float $totalDistance,
        float $progress
    ): array {
        return [
            'latitude' => $departCoords['lat'] + ($arriveeCoords['lat'] - $departCoords['lat']) * $progress,
            'longitude' => $departCoords['lon'] + ($arriveeCoords['lon'] - $departCoords['lon']) * $progress,
            'distanceCovered' => $totalDistance * $progress,
            'progress' => $progress * 100,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }
}