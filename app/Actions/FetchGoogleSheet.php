<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads a Google Sheet as CSV from its share link, so people without Excel can import free
 * days straight from Sheets. The sheet must be shared as "Anyone with the link". Only the sheet
 * ID (and tab ID) are taken from the pasted link; the request always goes to Google's own export
 * address, so a link can't be used to make the server fetch anything else.
 */
class FetchGoogleSheet
{
    /** Same limit as an uploaded file. */
    public const MAX_BYTES = 100 * 1024;

    /**
     * The sheet's ID and tab (gid) from a link, or null if it isn't a Google Sheets link.
     *
     * @return array{id: string, gid: string|null}|null
     */
    public function parseUrl(string $url): ?array
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'docs.google.com') {
            return null;
        }

        if (preg_match('#^/spreadsheets/d/([A-Za-z0-9_-]{20,100})(/|$)#', $parts['path'] ?? '', $matches) !== 1) {
            return null;
        }

        // The tab shows up as #gid=123 when copied from the address bar, or ?gid=123 in some share links.
        preg_match('/(?:^|&)gid=(\d+)/', ($parts['fragment'] ?? '').'&'.($parts['query'] ?? ''), $gid);

        return ['id' => $matches[1], 'gid' => $gid[1] ?? null];
    }

    /**
     * The sheet's contents as CSV.
     *
     * @throws RuntimeException with a message fit to show the user.
     */
    public function handle(string $url): string
    {
        $sheet = $this->parseUrl($url) ?? throw new RuntimeException(__('That is not a Google Sheets link. Copy the link from the Share button in Google Sheets.'));

        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['https']]])
                ->get('https://docs.google.com/spreadsheets/d/'.$sheet['id'].'/export', array_filter([
                    'format' => 'csv',
                    'gid' => $sheet['gid'],
                ]));
        } catch (ConnectionException) {
            throw new RuntimeException(__('Google Sheets could not be reached. Try again in a moment.'));
        }

        // A private sheet answers with an error or redirects to Google's sign-in page (HTML).
        if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), 'text/csv')) {
            throw new RuntimeException(__('The sheet could not be opened. In Google Sheets, click Share and set General access to "Anyone with the link".'));
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException(__('The sheet is too big. Keep it to one column of dates.'));
        }

        return $response->body();
    }
}
