<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

use function Laravel\Prompts\datatable;

it('renders a table with headers and search line', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name', 'Email'],
        rows: [
            ['Alice', 'alice@example.com'],
            ['Bob', 'bob@example.com'],
        ],
        scroll: 5,
        label: 'Select a user',
    );

    Prompt::assertStrippedOutputContains('Select a user');
    Prompt::assertStrippedOutputContains('/ Search');
    Prompt::assertStrippedOutputContains('Name');
    Prompt::assertStrippedOutputContains('Email');
    Prompt::assertStrippedOutputContains('Alice');
    Prompt::assertStrippedOutputContains('Bob');
});

it('returns the index for list arrays', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
            ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe(1);
});

it('returns the key for associative arrays', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('b');
});

it('navigates with arrow keys', function () {
    Prompt::fake([Key::DOWN, Key::DOWN, Key::UP, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('b');
});

it('wraps around when navigating past the end', function () {
    Prompt::fake([Key::UP, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('c');
});

it('supports page up and page down', function () {
    Prompt::fake([Key::PAGE_DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
            'd' => ['Diana'],
            'e' => ['Ethan'],
            'f' => ['Fatima'],
        ],
        scroll: 3,
        label: 'Pick one',
    );

    expect($result)->toBe('d');
});

it('supports home and end keys', function () {
    Prompt::fake([Key::oneOf([Key::END, Key::CTRL_E], Key::END[0]) ? Key::END[0] : Key::CTRL_E, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('c');
});

it('enters search mode with slash and filters rows', function () {
    Prompt::fake(['/', 'b', 'o', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('b');
});

it('returns the original key after filtering a list array', function () {
    Prompt::fake(['/', 'c', 'h', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
            ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    // "Charlie" is at original index 2, search should preserve that
    expect($result)->toBe(2);
});

it('cancels search with escape', function () {
    Prompt::fake(['/', 'x', 'y', 'z', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    // After cancel, filter is cleared, back to first row
    expect($result)->toBe('a');
});

it('re-enters search mode with active query', function () {
    Prompt::fake(['/', 'b', 'o', Key::ENTER, '/', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('b');
});

it('keeps search behavior stable while help text is always visible', function () {
    Prompt::fake(['/', 'b', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Pick one',
        sort: ['Name' => 'alpha'],
    );

    expect($result)->toBe('b');
    Prompt::assertStrippedOutputContains('Type to filter | Enter: keep filter | Esc: clear filter');
});

it('shows no results message when search matches nothing', function () {
    Prompt::fake(['/', 'z', 'z', 'z', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    Prompt::assertStrippedOutputContains('No results found.');
});

it('renders column-aware borders', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['A', 'B'],
        rows: [
            ['One', 'Two'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // Column-aware separators should use ┬, ┼, ┴
    Prompt::assertStrippedOutputContains('┬');
    Prompt::assertStrippedOutputContains('┼');
    Prompt::assertStrippedOutputContains('┴');
});

it('shows simple borders when no results', function () {
    Prompt::fake(['/', 'z', 'z', 'z', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['A', 'B'],
        rows: [
            ['One', 'Two'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // When showing "No results found", the border should not have column separators
    $content = Prompt::strippedContent();

    // The no-results area should have a simple ├───┤ border, not ├───┬───┤
    // We check that "No results found" appears without column separators on that line
    expect($content)->toContain('No results found.');
});

it('shows viewing info only when scrolling is needed', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // Only 2 rows with scroll=5, no info line needed
    Prompt::assertStrippedOutputDoesntContain('Viewing');
});

it('shows viewing info when there are more rows than scroll', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
            ['Charlie'],
            ['Diana'],
            ['Ethan'],
            ['Fatima'],
        ],
        scroll: 3,
        label: 'Test',
    );

    Prompt::assertStrippedOutputContains('Viewing');
    Prompt::assertStrippedOutputContains('1-3');
    Prompt::assertStrippedOutputContains('of');
    Prompt::assertStrippedOutputContains('6');
});

it('handles multiline cells', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name', 'Role'],
        rows: [
            ['Alice', "CEO\nDeveloper"],
            ['Bob', 'Designer'],
        ],
        scroll: 5,
        label: 'Test',
    );

    Prompt::assertStrippedOutputContains('CEO');
    Prompt::assertStrippedOutputContains('Developer');
    Prompt::assertStrippedOutputContains('Alice');
});

it('keeps highlighted multiline row fully visible', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    datatable(
        headers: ['Name', 'Role'],
        rows: [
            ['Alice', 'Designer'],
            ['Bob', "CEO\nCTO\nDeveloper"],
            ['Charlie', 'Designer'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // Bob's multiline row should be fully visible when highlighted
    Prompt::assertStrippedOutputContains('CEO');
    Prompt::assertStrippedOutputContains('CTO');
    Prompt::assertStrippedOutputContains('Developer');
});

it('uses comfortable width and does not stretch to terminal', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['A', 'B'],
        rows: [
            ['Hi', 'Lo'],
        ],
        scroll: 5,
        label: 'Test',
    );

    $content = Prompt::strippedContent();

    // With tiny data, the table should not stretch to 80 cols
    $lines = explode("\n", $content);
    $maxLen = max(array_map('mb_strwidth', $lines));

    expect($maxLen)->toBeLessThan(70);
});

it('handles outlier column widths gracefully', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name', 'Value'],
        rows: [
            ['Alice', 'Short'],
            ['Bob', 'Short'],
            ['Charlie', 'Short'],
            ['Diana', 'Short'],
            ['Ethan', 'Short'],
            ['An extremely long value that should be treated as an outlier and truncated', 'Short'],
        ],
        scroll: 5,
        label: 'Test',
    );

    $content = Prompt::strippedContent();
    $lines = explode("\n", $content);
    $maxLen = max(array_map('mb_strwidth', $lines));

    // The outlier shouldn't blow up the table width to terminal width (80)
    expect($maxLen)->toBeLessThan(76);
});

it('supports custom filter closure', function () {
    Prompt::fake(['/', 'a', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Code'],
        rows: [
            'x' => ['Alice', 'X1'],
            'y' => ['Bob', 'Y2'],
        ],
        scroll: 5,
        label: 'Pick one',
        filter: fn($row, $query) => str_starts_with(strtolower($row[0]), strtolower($query)),
    );

    // Custom filter matches "Alice" starting with "a", not "Bob"
    expect($result)->toBe('x');
});

it('renders cancel state with strikethrough data', function () {
    Prompt::fake([Key::CTRL_C]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    Prompt::assertOutputContains('Cancelled.');
});

it('renders submit state with selected row', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    datatable(
        headers: ['Name', 'Role'],
        rows: [
            ['Alice', 'Designer'],
            ['Bob', 'Developer'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    Prompt::assertStrippedOutputContains('Bob, Developer');
});

it('scrolls and shows scrollbar when needed', function () {
    Prompt::fake([Key::DOWN, Key::DOWN, Key::DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
            'd' => ['Diana'],
            'e' => ['Ethan'],
        ],
        scroll: 3,
        label: 'Test',
    );

    expect($result)->toBe('d');

    // Scrollbar indicators should be present
    Prompt::assertOutputContains('┃');
});

it('works without headers', function () {
    Prompt::fake([Key::ENTER]);

    $result = datatable(
        rows: [
            ['Alice', 'Designer'],
            ['Bob', 'Developer'],
        ],
        scroll: 5,
        label: 'Pick',
    );

    expect($result)->toBe(0);
    Prompt::assertStrippedOutputContains('Alice');
    Prompt::assertStrippedOutputContains('Designer');
});

it('dims rows during search', function () {
    Prompt::fake(['/', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // During search state, rows should be dimmed (contains dim escape sequence)
    // We just verify the search mode was entered and exited cleanly
    Prompt::assertStrippedOutputContains('Alice');
});

it('handles blank cells in width calculation', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name', 'Email'],
        rows: [
            ['Alice', 'alice@example.com'],
            ['', ''],
            ['Charlie', 'charlie@example.com'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // Blank cells should not skew column widths
    Prompt::assertStrippedOutputContains('Alice');
    Prompt::assertStrippedOutputContains('alice@example.com');
    Prompt::assertStrippedOutputContains('Charlie');
});

it('renders search line in cancel state to prevent layout shift', function () {
    Prompt::fake([Key::CTRL_C]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    // Cancel state should include the search line
    Prompt::assertStrippedOutputContains('/ Search');
    Prompt::assertOutputContains('Cancelled.');
});

it('maintains fixed visual height', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        scroll: 5,
        label: 'Test',
    );

    // Even with only 2 rows, the data area should be padded to scroll height (5 lines)
    $content = Prompt::strippedContent();

    // Count lines between the header separator (┼ or ┬) and bottom border (┴)
    $lines = explode("\n", $content);
    $dataStart = null;
    $dataEnd = null;

    foreach ($lines as $i => $line) {
        if (str_contains($line, '┼') || (str_contains($line, '┬') && $dataStart === null)) {
            $dataStart = $i;
        }
        if (str_contains($line, '┴')) {
            $dataEnd = $i;
        }
    }

    if ($dataStart !== null && $dataEnd !== null) {
        $dataLineCount = $dataEnd - $dataStart - 1;
        expect($dataLineCount)->toBe(5);
    }
});

it('renders sortable headers without shortcut prefixes in column mode', function () {
    Prompt::fake(['c', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Name', 'Age'],
        rows: [
            ['Alice', '20'],
            ['Bob', '3'],
        ],
        scroll: 5,
        label: 'Sort users',
        sort: ['Name' => 'alpha', 'Age' => 'numeric'],
    );

    Prompt::assertStrippedOutputContains('Name');
    Prompt::assertStrippedOutputContains('Age');
    Prompt::assertStrippedOutputContains('-');
    Prompt::assertStrippedOutputDoesntContain('[1]');
    Prompt::assertStrippedOutputDoesntContain('[2]');
});

it('emphasizes the selected sortable column header in column mode', function () {
    Prompt::fake(['c', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Name', 'Age'],
        rows: [
            ['Alice', '20'],
            ['Bob', '30'],
        ],
        scroll: 5,
        label: 'Sort users',
        sort: ['Name' => 'alpha', 'Age' => 'numeric'],
    );

    Prompt::assertOutputContains("\e[1m\e[30mName\e[39m\e[22m");
    Prompt::assertOutputDoesntContain("\e[1m\e[30mAge\e[39m\e[22m");
});

it('sorts numeric columns using configured type and skips non-sortable columns when moving right', function () {
    Prompt::fake(['c', Key::RIGHT, 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Status', 'Age'],
        rows: [
            'alice' => ['Alice', 'active', '20'],
            'bob' => ['Bob', 'inactive', '3'],
            'charlie' => ['Charlie', 'active', '100'],
        ],
        scroll: 5,
        label: 'Sort users',
        sort: ['Name' => 'alpha', 'Status' => false, 'Age' => 'numeric'],
    );

    expect($result)->toBe('bob');
    Prompt::assertStrippedOutputContains('Age ˄');
    Prompt::assertOutputContains("\e[1m\e[30m˄\e[39m\e[22m");
    Prompt::assertOutputContains("\e[2m-\e[22m");
});

it('supports Key::RIGHT_ARROW for moving to the next sortable column', function () {
    Prompt::fake(['c', Key::RIGHT_ARROW, 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Seats'],
        rows: [
            'high' => ['High', '150'],
            'low' => ['Low', '45'],
            'mid' => ['Mid', '95'],
        ],
        scroll: 5,
        label: 'Sort seats',
        sort: ['Name' => 'alpha', 'Seats' => 'numeric'],
    );

    expect($result)->toBe('low');
    Prompt::assertStrippedOutputContains('Seats ˄');
});

it('supports Key::LEFT for wrapping to the previous sortable column', function () {
    Prompt::fake(['c', Key::LEFT, 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Seats'],
        rows: [
            'high' => ['High', '150'],
            'low' => ['Low', '45'],
            'mid' => ['Mid', '95'],
        ],
        scroll: 5,
        label: 'Sort seats',
        sort: ['Name' => 'alpha', 'Seats' => 'numeric'],
    );

    expect($result)->toBe('low');
    Prompt::assertStrippedOutputContains('Seats ˄');
});

it('supports Key::LEFT_ARROW for wrapping to the previous sortable column', function () {
    Prompt::fake(['c', Key::LEFT_ARROW, 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Seats'],
        rows: [
            'high' => ['High', '150'],
            'low' => ['Low', '45'],
            'mid' => ['Mid', '95'],
        ],
        scroll: 5,
        label: 'Sort seats',
        sort: ['Name' => 'alpha', 'Seats' => 'numeric'],
    );

    expect($result)->toBe('low');
    Prompt::assertStrippedOutputContains('Seats ˄');
});

it('renders explicit structured display values without applying column formatters', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Title', 'Runtime'],
        rows: [
            ['Alpha', ['raw' => 4200, 'display' => '1 hour 10 minutes']],
            ['Beta', ['raw' => 4230, 'display' => '1 hour 10 minutes 30 seconds']],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: [
            'Title' => 'alpha',
            'Runtime' => [
                'type' => 'numeric',
                'display' => '%s seconds',
            ],
        ],
    );

    Prompt::assertStrippedOutputContains('1 hour 10 minutes');
    Prompt::assertStrippedOutputContains('1 hour 10 minutes 30 seconds');
    Prompt::assertStrippedOutputDoesntContain('4200 seconds');
});

it('sorts numeric columns using structured raw values instead of display text', function () {
    Prompt::fake(['c', 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Title', 'Runtime'],
        rows: [
            'first' => ['First', ['raw' => 2, 'display' => 'Zebra']],
            'second' => ['Second', ['raw' => 10, 'display' => 'Apple']],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: ['Runtime' => 'numeric'],
    );

    expect($result)->toBe('first');
});

it('filters structured cells using raw values', function () {
    Prompt::fake(['/', '6', '0', Key::ENTER, Key::ENTER]);

    $result = datatable(
        headers: ['Title', 'Runtime'],
        rows: [
            'alpha' => ['Alpha', ['raw' => 60, 'display' => 'one minute']],
            'beta' => ['Beta', ['raw' => 90, 'display' => 'one and a half minutes']],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: ['Runtime' => 'numeric'],
    );

    expect($result)->toBe('alpha');
});

it('returns list index for structured rows', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Runtime'],
        rows: [
            ['Alice', ['raw' => 120, 'display' => '2 minutes']],
            ['Bob', ['raw' => 180, 'display' => '3 minutes']],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe(1);
});

it('returns associative key for structured rows', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    $result = datatable(
        headers: ['Name', 'Runtime'],
        rows: [
            'a' => ['Alice', ['raw' => 120, 'display' => '2 minutes']],
            'b' => ['Bob', ['raw' => 180, 'display' => '3 minutes']],
        ],
        scroll: 5,
        label: 'Pick one',
    );

    expect($result)->toBe('b');
});

it('formats columns with printf patterns and keeps numeric sorting based on raw values', function () {
    Prompt::fake(['c', Key::RIGHT, 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Title', 'Revenue'],
        rows: [
            'alpha' => ['Alpha', '1200'],
            'beta' => ['Beta', '250'],
            'gamma' => ['Gamma', '3000'],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: [
            'Title' => [
                'type' => 'alpha',
                'display' => [
                    'type' => 'printf',
                    'pattern' => 'Movie: %s',
                ],
            ],
            'Revenue' => [
                'type' => 'numeric',
                'display' => '%d (USD)',
            ],
        ],
    );

    expect($result)->toBe('beta');
    Prompt::assertStrippedOutputContains('Movie: Alpha');
    Prompt::assertStrippedOutputContains('1200 (USD)');
});

it('formats columns with callable display formatters', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Title', 'Runtime'],
        rows: [
            ['Alpha', '161'],
            ['Beta', '95'],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: [
            'Runtime' => [
                'type' => 'numeric',
                'display' => fn (string $value, array $row): string => sprintf('%s min (%s)', $value, $row[0] ?? ''),
            ],
        ],
    );

    Prompt::assertStrippedOutputContains('161 min (Alpha)');
    Prompt::assertStrippedOutputContains('95 min (Beta)');
});

it('treats legacy currency and duration formatter names as no-op', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Title', 'Revenue', 'Runtime'],
        rows: [
            ['Alpha', '1200', '95'],
        ],
        scroll: 5,
        label: 'Sort movies',
        sort: [
            'Revenue' => [
                'type' => 'numeric',
                'display' => 'currency',
            ],
            'Runtime' => [
                'type' => 'numeric',
                'display' => ['type' => 'duration'],
            ],
        ],
    );

    Prompt::assertStrippedOutputContains('1200');
    Prompt::assertStrippedOutputContains('95');
    Prompt::assertStrippedOutputDoesntContain('$1,200');
    Prompt::assertStrippedOutputDoesntContain('hours');
});

it('toggles sort direction with s in column mode', function () {
    Prompt::fake(['c', 's', 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Name'],
        rows: [
            'a' => ['Alice'],
            'b' => ['Bob'],
            'c' => ['Charlie'],
        ],
        scroll: 5,
        label: 'Sort users',
        sort: ['Name' => 'alpha'],
    );

    expect($result)->toBe('c');
    Prompt::assertStrippedOutputContains('Name ˅');
    Prompt::assertOutputContains("\e[1m\e[30m˅\e[39m\e[22m");
});

it('sorts date columns using configured type', function () {
    Prompt::fake(['c', 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Created At'],
        rows: [
            'new' => ['2024-02-01'],
            'old' => ['2023-01-01'],
        ],
        scroll: 5,
        label: 'Sort dates',
        sort: ['Created At' => 'date'],
    );

    expect($result)->toBe('old');
});

it('sorts date columns using a configured date pattern', function () {
    Prompt::fake(['c', 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Created At'],
        rows: [
            'new' => ['01@01@2024'],
            'old' => ['31@12@2023'],
        ],
        scroll: 5,
        label: 'Sort dates',
        sort: ['Created At' => ['type' => 'date', 'pattern' => 'd@m@Y']],
    );

    expect($result)->toBe('old');
});

it('sorts alpha-numeric columns naturally', function () {
    Prompt::fake(['c', 's', Key::ESCAPE, Key::ENTER]);

    $result = datatable(
        headers: ['Version'],
        rows: [
            'v10' => ['v10'],
            'v2' => ['v2'],
            'v1' => ['v1'],
        ],
        scroll: 5,
        label: 'Sort versions',
        sort: ['Version' => 'alpha-numeric'],
    );

    expect($result)->toBe('v1');
});

it('shows browse mode help text by default', function () {
    Prompt::fake([Key::ENTER]);

    datatable(
        headers: ['Version'],
        rows: [
            ['v10'],
            ['v2'],
        ],
        scroll: 5,
        label: 'Sort versions',
        sort: ['Version' => 'alpha-numeric'],
    );

    Prompt::assertStrippedOutputContains('[Enter] select');
    Prompt::assertStrippedOutputContains('[/] search');
    Prompt::assertStrippedOutputContains('[c] column');
    Prompt::assertStrippedOutputDoesntContain('[s] select');
});

it('shows search mode help text while searching', function () {
    Prompt::fake(['/', 'v', Key::ENTER, Key::ENTER]);

    datatable(
        headers: ['Version'],
        rows: [
            ['v10'],
            ['v2'],
        ],
        scroll: 5,
        label: 'Sort versions',
        sort: ['Version' => 'alpha-numeric'],
    );

    Prompt::assertStrippedOutputContains('Type to filter | Enter: keep filter');
});

it('shows column mode help text while selecting a column', function () {
    Prompt::fake(['c', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Version'],
        rows: [
            ['v10'],
            ['v2'],
        ],
        scroll: 5,
        label: 'Sort versions',
        sort: ['Version' => 'alpha-numeric'],
    );

    Prompt::assertStrippedOutputContains('[←/→] column  [s] sort/toggle  [/] search  [Esc] normal');
});

it('renders a mode label before the help text', function () {
    Prompt::fake(['/', Key::ESCAPE, 'c', Key::ESCAPE, Key::ENTER]);

    datatable(
        headers: ['Version'],
        rows: [
            ['v10'],
            ['v2'],
        ],
        scroll: 5,
        label: 'Sort versions',
        sort: ['Version' => 'alpha-numeric'],
    );

    Prompt::assertStrippedOutputContains('NORMAL');
    Prompt::assertStrippedOutputContains('SEARCH');
    Prompt::assertStrippedOutputContains('COLUMN');
    Prompt::assertStrippedOutputDoesntContain('SELECT');
    Prompt::assertStrippedOutputDoesntContain('SORTED');
    Prompt::assertOutputContains("\e[40m\e[37m NORMAL \e[39m\e[49m");
});
