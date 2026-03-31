<?php

class PlacementController
{
    private PDO $pdo;
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function createPlacement(array $data): array{

        $stmt = $this->pdo->prepare("
            INSERT INTO placements 
            (athlete_id, olympic_games_id, discipline_id, placing)
            VALUES (:athlete_id, :olympic_games_id, :discipline_id, :placing)
        ");

        $stmt->execute([
            'athlete_id' => $data['athlete_id'],
            'olympic_games_id' => $data['olympic_games_id'],
            'discipline_id' => $data['discipline_id'],
            'placing' => $data['placing']
        ]);

        $id = $this->pdo->lastInsertId();
        $placement = $this->getPlacementById($id);
        return ['status' => 201, 'body' => [$placement]];
    }

    public function getPlacementById(int $id): ?array{

        $stmt = $this->pdo->prepare("
        SELECT * FROM placements WHERE id = :id 
        ");

        $stmt->execute([':id' => $id]);
        $placement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$placement) {
            return null;
        }
        return ['status' => 200, 'body' => $placement];
    }

    public function delete(int $id): array{
        $placement = $this->getPlacementById($id);
        if (!$placement) {
            return ['status' => 404];
        }

        $stmt = $this->pdo->prepare("
        DELETE FROM placements WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['status' => 204];
    }
}