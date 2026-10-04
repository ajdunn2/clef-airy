<?php

namespace App\Services;

use InvalidArgumentException;

class SystemOnePayload
{
    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public function fromForm(string $state, array $questions): string
    {
        $built = [];

        foreach ($questions as $question) {
            if (! is_array($question) || $this->blank($question)) {
                continue;
            }

            $name = trim((string) ($question['name'] ?? ''));
            $type = (string) ($question['type'] ?? '');
            $instructions = trim((string) ($question['instructions'] ?? ''));

            $this->assertName($name, 'Use a letter in each question name. Names may also include numbers and underscores.');

            if (array_key_exists($name, $built)) {
                throw new InvalidArgumentException('Question names must be unique.');
            }

            if (! in_array($type, ['noul', 'choice', 'score'], true)) {
                throw new InvalidArgumentException('Choose a question type.');
            }

            if ($instructions === '') {
                throw new InvalidArgumentException('Enter instructions for each question.');
            }

            $built[$name] = [
                'type' => $type,
                'instructions' => $instructions,
            ];

            $criteria = $this->criteria($type, $question);

            if ($criteria !== null) {
                $built[$name]['criteria'] = $criteria;
            }
        }

        if ($built === []) {
            throw new InvalidArgumentException('Add a question.');
        }

        $json = json_encode([
            'state' => $this->stateValue($state),
            'questions' => $built,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new InvalidArgumentException('The questions could not be turned into JSON.');
        }

        return $json;
    }

    public function pretty(string $body): string
    {
        if (! json_validate($body)) {
            return $body;
        }

        $json = json_encode(
            json_decode($body),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $json === false ? $body : $json;
    }

    /**
     * @return array{model: ?string, answers: list<array<string, mixed>>, usage: ?string}|null
     */
    public function present(string $body): ?array
    {
        if (! json_validate($body)) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded['answers']) || ! is_array($decoded['answers'])) {
            return null;
        }

        $answers = [];

        foreach ($decoded['answers'] as $name => $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $answers[] = $this->answer((string) $name, $answer);
        }

        if ($answers === []) {
            return null;
        }

        $usage = null;

        if (isset($decoded['usage']['input_tokens'])) {
            $usage = $decoded['usage']['input_tokens'].' tokens in';
        }

        return [
            'model' => isset($decoded['model']) ? (string) $decoded['model'] : null,
            'answers' => $answers,
            'usage' => $usage,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function blank(array $question): bool
    {
        foreach (['name', 'instructions', 'true', 'false', 'choice', 'score'] as $key) {
            if (trim((string) ($question[$key] ?? '')) !== '') {
                return false;
            }
        }

        foreach ($question['options'] ?? [] as $option) {
            if (is_array($option) && trim((string) ($option['name'] ?? '')) !== '') {
                return false;
            }
        }

        foreach ($question['levels'] ?? [] as $level) {
            if (trim((string) $level) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stateValue(string $state): mixed
    {
        if (! json_validate($state)) {
            return $state;
        }

        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : $state;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, string|null>|list<string>|null
     */
    private function criteria(string $type, array $question): ?array
    {
        if ($type === 'noul') {
            $true = trim((string) ($question['true'] ?? ''));
            $false = trim((string) ($question['false'] ?? ''));

            if ($true === '' && $false === '') {
                return null;
            }

            return ['true' => $true, 'false' => $false];
        }

        if ($type === 'choice') {
            $criteria = [];

            foreach ($question['options'] ?? [] as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $name = trim((string) ($option['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $this->assertName($name, 'Use a letter in each option name. Names may also include numbers and underscores.');

                $description = trim((string) ($option['description'] ?? ''));
                $criteria[$name] = $description === '' ? null : $description;
            }

            if ($criteria === []) {
                foreach ($this->lines((string) ($question['choice'] ?? '')) as $line) {
                    [$name, $description] = array_pad(explode('|', $line, 2), 2, '');
                    $name = trim($name);
                    $description = trim($description);

                    if ($name === '') {
                        throw new InvalidArgumentException('Each choice option needs a name.');
                    }

                    $this->assertName($name, 'Use a letter in each option name. Names may also include numbers and underscores.');

                    $criteria[$name] = $description === '' ? null : $description;
                }
            }

            if (count($criteria) < 2) {
                throw new InvalidArgumentException('Choice questions need at least two options.');
            }

            return $criteria;
        }

        $levels = [];

        foreach ($question['levels'] ?? [] as $level) {
            $level = trim((string) $level);

            if ($level !== '') {
                $levels[] = $level;
            }
        }

        if ($levels === []) {
            $levels = $this->lines((string) ($question['score'] ?? ''));
        }

        if (count($levels) < 2) {
            throw new InvalidArgumentException('Score questions need at least two levels, lowest first.');
        }

        return $levels;
    }

    private function assertName(string $name, string $message): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1 || preg_match('/[A-Za-z]/', $name) !== 1) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @return list<string>
     */
    private function lines(string $value): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{name: string, type: string, headline: string, detail: ?string, confidence: ?string, rows: list<array{label: string, value: string, share: int, selected: bool}>}
     */
    private function answer(string $name, array $answer): array
    {
        $type = (string) ($answer['type'] ?? '');
        $rows = [];
        $headline = $name;
        $detail = null;

        if ($type === 'noul' && isset($answer['noul']) && is_numeric($answer['noul'])) {
            $noul = (float) $answer['noul'];
            $headline = $noul >= 0.5 ? 'Yes' : 'No';
            $detail = $this->percent($noul).' yes';
        }

        if ($type === 'choice') {
            $headline = (string) ($answer['choice'] ?? $name);
            $rows = $this->probabilityRows($answer['probabilities'] ?? [], $headline);
        }

        if ($type === 'score' && isset($answer['score']) && is_numeric($answer['score'])) {
            $score = (float) $answer['score'];
            $legend = is_array($answer['legend'] ?? null) ? $answer['legend'] : [];
            $headline = $this->scoreHeadline($score, $legend);
            $detail = $this->number($score);
            $rows = $this->probabilityRows($answer['probabilities'] ?? [], null);
            $rows = array_map(function (array $row) use ($legend): array {
                $label = $legend[$row['label']] ?? $row['label'];

                return [
                    'label' => (string) $label,
                    'value' => $row['value'],
                    'share' => $row['share'],
                    'selected' => $row['selected'],
                ];
            }, $rows);
        }

        $confidence = isset($answer['confidence']) && is_numeric($answer['confidence'])
            ? $this->percent((float) $answer['confidence'])
            : null;

        return [
            'name' => $name,
            'type' => $type,
            'headline' => $headline,
            'detail' => $detail,
            'confidence' => $confidence,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<mixed, mixed>  $legend
     */
    private function scoreHeadline(float $score, array $legend): string
    {
        $low = (int) floor($score);
        $high = (int) ceil($score);
        $lowLabel = isset($legend[(string) $low]) ? (string) $legend[(string) $low] : null;
        $highLabel = isset($legend[(string) $high]) ? (string) $legend[(string) $high] : null;

        if ($lowLabel === null) {
            return $this->number($score);
        }

        if ($low === $high || $highLabel === null || $lowLabel === $highLabel) {
            return $lowLabel;
        }

        return $lowLabel.' toward '.$highLabel;
    }

    /**
     * @return list<array{label: string, value: string, share: int, selected: bool}>
     */
    private function probabilityRows(mixed $probabilities, ?string $selected): array
    {
        if (! is_array($probabilities)) {
            return [];
        }

        $highest = null;

        foreach ($probabilities as $value) {
            if (is_numeric($value) && ($highest === null || (float) $value > $highest)) {
                $highest = (float) $value;
            }
        }

        $rows = [];

        foreach ($probabilities as $label => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $rows[] = [
                'label' => (string) $label,
                'value' => $this->percent((float) $value),
                'share' => (int) round(((float) $value) * 100),
                'selected' => $selected !== null
                    ? (string) $label === $selected
                    : $highest !== null && (float) $value === $highest,
            ];
        }

        return $rows;
    }

    private function percent(float $value): string
    {
        return (string) (int) round($value * 100).'%';
    }

    private function number(float $value): string
    {
        $rounded = round($value, 1);

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
    }
}
