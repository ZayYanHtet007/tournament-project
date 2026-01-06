<?php
class Team {
    public int $id;
    public string $name;
    public int $points = 0, $gWon = 0, $gLost = 0;
    public function __construct(int $id, string $name) { $this->id = $id; $this->name = $name; }
    public function getNet(): int { return $this->gWon - $this->gLost; }
}

class MatchGame {
    public Team $team1; public Team $team2;
    public int $wins1 = 0; public int $wins2 = 0;
    public bool $finished = false;
    public function __construct(Team $a, Team $b) { $this->team1 = $a; $this->team2 = $b; }
    public function setScore(int $w1, int $w2, int $limit = 2): void {
        if ($this->finished) $this->applyPoints($this->wins1, $this->wins2, -1);
        $this->wins1 = $w1; $this->wins2 = $w2;
        if (($w1 === $limit || $w2 === $limit) && $w1 !== $w2) {
            $this->finished = true;
            $this->applyPoints($w1, $w2, 1);
        } else { $this->finished = false; }
    }
    private function applyPoints(int $w1, int $w2, int $m): void {
        $this->team1->gWon += ($w1 * $m); $this->team1->gLost += ($w2 * $m);
        $this->team2->gWon += ($w2 * $m); $this->team2->gLost += ($w1 * $m);
        if ($w1 > $w2) {
            $this->team1->points += (($w2 === 0 ? 3 : 2) * $m);
            $this->team2->points += (($w2 === 1 ? 1 : 0) * $m);
        } else {
            $this->team2->points += (($w1 === 0 ? 3 : 2) * $m);
            $this->team1->points += (($w1 === 1 ? 1 : 0) * $m);
        }
    }
    public function getWinner(): ?Team { return ($this->wins1 > $this->wins2) ? $this->team1 : $this->team2; }
    public function getLoser(): ?Team { return ($this->wins1 > $this->wins2) ? $this->team2 : $this->team1; }
}

class Group {
    public string $name; public array $teams; public array $matches = [];
    public function __construct(string $name, array $teams) {
        $this->name = $name; $this->teams = $teams;
        for ($i = 0; $i < count($teams); $i++) 
            for ($j = $i + 1; $j < count($teams); $j++) 
                $this->matches[] = new MatchGame($teams[$i], $teams[$j]);
    }
    public function allFinished(): bool {
        foreach ($this->matches as $m) if (!$m->finished) return false;
        return true;
    }
    public function getStandings(): array {
        $s = $this->teams;
        usort($s, function($a, $b) {
            if ($b->points !== $a->points) return $b->points <=> $a->points;
            if ($b->getNet() !== $a->getNet()) return $b->getNet() <=> $a->getNet();
            return $b->gWon <=> $a->gWon;
        });
        return $s;
    }
}

class StandardTournament {
    public array $groups = [];
    public array $knockoutStages = ['QUARTERS' => [], 'SEMIS' => [], 'FINALS' => []];
    public string $state = 'GROUP'; public int $totalTeams; public bool $isFinished = false;

    public function __construct(array $teams) {
        $this->totalTeams = count($teams);
        shuffle($teams);
        $size = ($this->totalTeams == 16) ? 4 : 6;
        foreach (array_chunk($teams, $size) as $i => $chunk) $this->groups[] = new Group("Group " . chr(65 + $i), $chunk);
    }

    public function finishGroups(): bool {
        foreach ($this->groups as $g) if (!$g->allFinished()) return false;
        $q = []; $count = ($this->totalTeams == 12) ? 4 : 2;
        foreach ($this->groups as $g) $q = array_merge($q, array_slice($g->getStandings(), 0, $count));
        $this->state = 'QUARTERS'; shuffle($q);
        for ($i = 0; $i < 8; $i += 2) $this->knockoutStages['QUARTERS'][] = new MatchGame($q[$i], $q[$i+1]);
        return true;
    }

    public function advanceToSemis(): bool {
        foreach ($this->knockoutStages['QUARTERS'] as $m) if (!$m->finished) return false;
        $w = array_map(fn($m) => $m->getWinner(), $this->knockoutStages['QUARTERS']);
        $this->knockoutStages['SEMIS'] = [new MatchGame($w[0], $w[1]), new MatchGame($w[2], $w[3])];
        $this->state = 'SEMIS'; return true;
    }

    public function advanceToFinals(): bool {
        foreach ($this->knockoutStages['SEMIS'] as $m) if (!$m->finished) return false;
        $w = array_map(fn($m) => $m->getWinner(), $this->knockoutStages['SEMIS']);
        $l = array_map(fn($m) => $m->getLoser(), $this->knockoutStages['SEMIS']);
        $this->knockoutStages['FINALS'] = ['GRAND' => new MatchGame($w[0], $w[1]), 'THIRD' => new MatchGame($l[0], $l[1])];
        $this->state = 'FINALS'; return true;
    }
}