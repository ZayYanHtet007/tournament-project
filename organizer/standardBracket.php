<?php
class Team {
    public int $id;
    public string $name;
    public int $points = 0;
    public function __construct(int $id, string $name) { $this->id = $id; $this->name = $name; }
}

class MatchGame {
    public Team $team1;
    public Team $team2;
    public int $wins1 = 0;
    public int $wins2 = 0;
    public bool $finished = false;

    public function __construct(Team $a, Team $b) { $this->team1 = $a; $this->team2 = $b; }

    public function setScore(int $w1, int $w2, int $max = 2): void {
        if (($w1 === $max || $w2 === $max) && $w1 !== $w2) {
            if ($this->finished) {
                if ($this->wins1 > $this->wins2) $this->team1->points -= 3;
                else $this->team2->points -= 3;
            }
            $this->wins1 = $w1;
            $this->wins2 = $w2;
            $this->finished = true;
            if ($w1 > $w2) $this->team1->points += 3;
            else $this->team2->points += 3;
        } else {
            $this->finished = false;
        }
    }
    public function getWinner(): ?Team { return ($this->wins1 > $this->wins2) ? $this->team1 : $this->team2; }
    public function getLoser(): ?Team { return ($this->wins1 > $this->wins2) ? $this->team2 : $this->team1; }
}

class Group {
    public string $name;
    public array $teams;
    public array $matches = [];
    public function __construct(string $name, array $teams) {
        $this->name = $name; $this->teams = $teams;
        for ($i = 0; $i < count($this->teams); $i++) {
            for ($j = $i + 1; $j < count($this->teams); $j++) {
                $this->matches[] = new MatchGame($this->teams[$i], $this->teams[$j]);
            }
        }
    }
    public function allMatchesFinished(): bool {
        foreach ($this->matches as $m) if (!$m->finished) return false;
        return true;
    }
    public function getTopTeams(int $count): array {
        usort($this->teams, fn($a, $b) => $b->points <=> $a->points);
        return array_slice($this->teams, 0, $count);
    }
}

class StandardTournament {
    public array $groups = [];
    public array $knockoutStages = ['QUARTERS' => [], 'SEMIS' => [], 'FINALS' => []];
    public string $state = 'GROUP';
    public int $totalTeams; // This stores the choice (12, 16, 24)
    public bool $isFinished = false;

    public function __construct(array $teams) {
        $this->totalTeams = count($teams);
        shuffle($teams);
        $groupSize = ($this->totalTeams === 16) ? 4 : 3;
        foreach (array_chunk($teams, $groupSize) as $i => $chunk) {
            $this->groups[] = new Group("Group " . chr(65 + $i), $chunk);
        }
    }

    public function finishGroups(): bool {
        foreach ($this->groups as $g) if (!$g->allMatchesFinished()) return false;
        $qualified = [];
        foreach ($this->groups as $g) {
            $count = ($this->totalTeams === 16) ? 2 : 1;
            $qualified = array_merge($qualified, $g->getTopTeams($count));
        }
        shuffle($qualified);
        if (count($qualified) === 8) {
            $this->state = 'QUARTERS';
            for ($i = 0; $i < 8; $i += 2) $this->knockoutStages['QUARTERS'][] = new MatchGame($qualified[$i], $qualified[$i+1]);
        } else {
            $this->state = 'SEMIS';
            for ($i = 0; $i < 4; $i += 2) $this->knockoutStages['SEMIS'][] = new MatchGame($qualified[$i], $qualified[$i+1]);
        }
        return true;
    }

    public function advanceToSemis(): bool {
        foreach ($this->knockoutStages['QUARTERS'] as $m) if (!$m->finished) return false;
        $winners = [];
        foreach ($this->knockoutStages['QUARTERS'] as $m) { $winners[] = $m->getWinner(); }
        $this->knockoutStages['SEMIS'] = [new MatchGame($winners[0], $winners[1]), new MatchGame($winners[2], $winners[3])];
        $this->state = 'SEMIS';
        return true;
    }

    public function advanceToFinals(): bool {
        foreach ($this->knockoutStages['SEMIS'] as $m) if (!$m->finished) return false;
        $winners = []; $losers = [];
        foreach ($this->knockoutStages['SEMIS'] as $m) { $winners[] = $m->getWinner(); $losers[] = $m->getLoser(); }
        $this->knockoutStages['FINALS'] = ['GRAND' => new MatchGame($winners[0], $winners[1]), 'THIRD' => new MatchGame($losers[0], $losers[1])];
        $this->state = 'FINALS';
        return true;
    }
}