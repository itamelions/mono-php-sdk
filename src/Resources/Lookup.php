<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * Lookup / identity verification.
 *
 * Exposes Mono's identity verification products: BVN, NIN, CAC, watchlist
 * screening, TIN, passport, driver's licence, address, account-number and
 * credit history lookups.
 *
 * Note: several lookup requests (BVN, NIN, …) are billable — including failed
 * lookups. Validate inputs before calling and monitor failed-request rates.
 */
class Lookup
{
    public function __construct(protected Mono $client) {}

    // ── BVN ──────────────────────────────────────────────────────────────────

    /**
     * Initiate a BVN consent request.
     *
     * @param array $params Required: bvn. Optional: scope ('identity' default, 'bank_accounts')
     */
    public function lookupBVN(array $params): array
    {
        return $this->client->call('POST', 'v2/lookup/bvn/initiate', $params);
    }

    /**
     * Verify a BVN request via OTP.
     *
     * @param array $params method, phone_number
     */
    public function verifyBVN(array $params): array
    {
        return $this->client->call('POST', 'v2/lookup/bvn/verify', $params);
    }

    /**
     * Fetch BVN details after successful OTP verification.
     */
    public function fetchBVN(array $params): array
    {
        return $this->client->call('POST', 'v2/lookup/bvn/fetch', $params);
    }

    // ── CAC (Corporate Affairs Commission) ───────────────────────────────────

    /**
     * Lookup / verify a business by its search key (returns the CAC company id).
     *
     * @param array $query Required: search
     */
    public function lookupCAC(array $query): array
    {
        $path = 'v3/lookup/cac';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Retrieve shareholder details for a company.
     */
    public function cacShareholders(string $companyId): array
    {
        return $this->client->call('GET', "v3/lookup/cac/company/{$companyId}");
    }

    /**
     * Retrieve persons with significant control (PSC) for a company.
     */
    public function cacPSC(string $companyId): array
    {
        return $this->client->call('GET', "v3/lookup/cac/company/{$companyId}/psc");
    }

    /**
     * Retrieve the secretary of a company.
     */
    public function cacSecretary(string $companyId): array
    {
        return $this->client->call('GET', "v3/lookup/cac/company/{$companyId}/secretary");
    }

    /**
     * Retrieve the directors of a company.
     */
    public function cacDirectors(string $companyId): array
    {
        return $this->client->call('GET', "v3/lookup/cac/company/{$companyId}/directors");
    }

    /**
     * Retrieve a company's CAC profile (search + shareholders + directors).
     *
     * @param string $rcNumber The company's registration number
     */
    public function cacProfile(string $rcNumber): array
    {
        return $this->client->call('GET', "v3/lookup/cac/profile/{$rcNumber}");
    }

    /**
     * Retrieve a company's CAC status report (returns a binary PDF).
     */
    public function cacStatusReport(string $companyId): string
    {
        return $this->client->callRaw('GET', "v3/lookup/cac/company/{$companyId}/status-report");
    }

    // ── Watchlist screening ──────────────────────────────────────────────────

    /**
     * Submit a watchlist screening for an individual or entity.
     *
     * @param array $params type, name, date_of_birth, gender, bvn, country, …
     */
    public function watchlist(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/watchlist', $params);
    }

    /**
     * Submit multiple watchlist screening requests in a single call.
     *
     * @param array $entries List of screening entries
     */
    public function watchlistBatch(array $entries): array
    {
        return $this->client->call('POST', 'v3/lookup/watchlist/batch', ['entries' => $entries]);
    }

    /**
     * Fetch the current status and completed result of a screening.
     */
    public function watchlistResult(string $id): array
    {
        return $this->client->call('GET', "v3/lookup/watchlist/{$id}");
    }

    /**
     * Retrieve the audit log for a watchlist screening.
     */
    public function watchlistAuditLog(string $id): array
    {
        return $this->client->call('GET', "v3/lookup/watchlist/{$id}/audit-log");
    }

    /**
     * Generate / download a screening report (returns a binary PDF).
     */
    public function watchlistReport(string $id): string
    {
        return $this->client->callRaw('GET', "v3/lookup/watchlist/{$id}/report");
    }

    /**
     * Start recurring monitoring for a watchlist profile.
     *
     * @param array $params type, name, date_of_birth, gender, bvn, country, …
     */
    public function startMonitoring(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/watchlist/monitor', $params);
    }

    /**
     * Stop a watchlist monitoring job.
     */
    public function stopMonitoring(string $id): array
    {
        return $this->client->call('DELETE', "v3/lookup/watchlist/monitor/{$id}");
    }

    // ── Other identity lookups ───────────────────────────────────────────────

    /**
     * Verify a house address via meter number + address + disco code.
     *
     * @param array $params meter_number, address, disco_code
     */
    public function address(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/address', $params);
    }

    /**
     * Verify an international passport.
     *
     * @param array $params passport_number, last_name, date_of_birth
     */
    public function passport(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/passport', $params);
    }

    /**
     * Verify a tax identification number (TIN).
     *
     * @param array $params number, channel ('TIN')
     */
    public function tin(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/tin', $params);
    }

    /**
     * Verify a national identification number (NIN).
     *
     * @param array $params nin
     */
    public function nin(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/nin', $params);
    }

    /**
     * Poll the status of a NIN PDF generation job.
     */
    public function pollNINJob(string $jobId): array
    {
        return $this->client->call('GET', "v3/lookup/nin/{$jobId}/job");
    }

    /**
     * Verify a driver's license.
     *
     * @param array $params license_number, date_of_birth, first_name, last_name
     */
    public function driversLicense(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/driver_license', $params);
    }

    /**
     * Lookup an account number (returns the masked BVN attached to it).
     *
     * @param array $params nip_code, account_number
     */
    public function accountNumber(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/account-number', $params);
    }

    /**
     * Retrieve a user's credit history.
     *
     * @param string $provider 'crc' (Credit Bureau Ltd), 'xds' (First Central),
     *                         or 'all' for both
     * @param array  $params   bvn
     */
    public function creditHistory(string $provider, array $params): array
    {
        return $this->client->call('POST', "v3/lookup/credit-history/{$provider}", $params);
    }

    /**
     * Verify NIN, BVN and date of birth in a single KYC call.
     *
     * @param array $params nin, bvn, date_of_birth
     */
    public function mashup(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/mashup', $params);
    }

    /**
     * List all banks supported under NIBSS / NIP.
     *
     * @param array $query Optional filters: page, limit
     */
    public function banks(array $query = []): array
    {
        $path = 'v3/lookup/banks';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    // ── Friendly verification aliases ────────────────────────────────────────

    public function lookupWatchlist(array $params): array
    {
        return $this->watchlist($params);
    }

    public function lookupNIN(array $params): array
    {
        return $this->nin($params);
    }

    public function lookupTIN(array $params): array
    {
        return $this->tin($params);
    }

    public function lookupPassport(array $params): array
    {
        return $this->passport($params);
    }

    public function lookupAddress(array $params): array
    {
        return $this->address($params);
    }

    public function lookupCreditHistory(string $provider, array $params): array
    {
        return $this->creditHistory($provider, $params);
    }

    public function lookupMashup(array $params): array
    {
        return $this->mashup($params);
    }

    public function lookupAccountNumber(array $params): array
    {
        return $this->accountNumber($params);
    }

    public function verifyCAC(array $query): array
    {
        return $this->lookupCAC($query);
    }

    public function verifyNIN(array $params): array
    {
        return $this->nin($params);
    }

    public function verifyTIN(array $params): array
    {
        return $this->tin($params);
    }

    public function verifyPassport(array $params): array
    {
        return $this->passport($params);
    }

    public function verifyDriversLicense(array $params): array
    {
        return $this->driversLicense($params);
    }

    public function verifyAddress(array $params): array
    {
        return $this->address($params);
    }

    public function verifyCreditHistory(string $provider, array $params): array
    {
        return $this->creditHistory($provider, $params);
    }
}
