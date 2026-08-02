<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * WhatsApp (Owo) payments.
 *
 * Lets users approve payments from their Owo wallet via WhatsApp
 * authorisation flows. Covers user status, beneficiaries, fund requests and
 * the payments made against a fund request.
 */
class WhatsAppPayment
{
    public function __construct(protected Mono $client) {}

    // ── User status ──────────────────────────────────────────────────────────

    /**
     * Check whether a phone number is associated with an Owo account and its
     * current status ('active' | 'pending_activiation' | 'not_found').
     */
    public function userStatus(string $phone): array
    {
        return $this->client->call('GET', 'owo/v1/users/status?phone=' . urlencode($phone));
    }

    // ── Beneficiaries ────────────────────────────────────────────────────────

    /**
     * Link your service to a user's account via a WhatsApp authorisation flow.
     * May include an initial fund_request created immediately upon linking.
     */
    public function linkBeneficiary(array $params): array
    {
        return $this->client->call('POST', 'owo/v1/beneficiaries/link', $params);
    }

    /**
     * Remove a beneficiary link (also cancels associated recurring schedules).
     */
    public function unlinkBeneficiary(array $params): array
    {
        return $this->client->call('POST', 'owo/v1/beneficiaries/unlink', $params);
    }

    /**
     * Fetch a single beneficiary by ID.
     */
    public function getBeneficiary(string $id): array
    {
        return $this->client->call('GET', "owo/v1/beneficiaries/{$id}");
    }

    /**
     * Fetch a paginated list of all beneficiaries.
     *
     * @param array $query Optional filters: page, limit, phone
     */
    public function beneficiaries(array $query = []): array
    {
        $path = 'owo/v1/beneficiaries';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    // ── Fund requests ────────────────────────────────────────────────────────

    /**
     * Initiate a WhatsApp authorisation flow to create a fund request.
     *
     * @param array $params phone, reference, description, amount, currency,
     *                      type ('onetime'|'recurring'), schedule (recurring)
     */
    public function createFundRequest(array $params): array
    {
        return $this->client->call('POST', 'owo/v1/fund-requests', $params);
    }

    /**
     * Initiate a one-time WhatsApp fund request.
     */
    public function createOneTimeFundRequest(array $params): array
    {
        $params['type'] = 'onetime';
        return $this->createFundRequest($params);
    }

    /**
     * Initiate a recurring WhatsApp fund request.
     */
    public function createRecurringFundRequest(array $params): array
    {
        $params['type'] = 'recurring';
        return $this->createFundRequest($params);
    }

    /**
     * Initiate a WhatsApp payment (alias of createFundRequest()).
     */
    public function createWhatsappPayment(array $params): array
    {
        return $this->createFundRequest($params);
    }

    /**
     * Fetch a paginated list of all fund requests.
     *
     * @param array $query Optional filters: page, limit, phone
     */
    public function fundRequests(array $query = []): array
    {
        $path = 'owo/v1/fund-requests';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch a single fund request by its ID.
     */
    public function getFundRequest(string $id): array
    {
        return $this->client->call('GET', "owo/v1/fund-requests/{$id}");
    }

    /**
     * Fetch a single WhatsApp payment by its fund-request ID (alias of getFundRequest()).
     */
    public function getWhatsappPayment(string $id): array
    {
        return $this->getFundRequest($id);
    }

    // ── Payments ─────────────────────────────────────────────────────────────

    /**
     * Fetch all payments associated with a fund request.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function payments(string $fundRequestId, array $query = []): array
    {
        $path = "owo/v1/fund-requests/{$fundRequestId}/payments";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch a single payment by fund-request ID and payment ID.
     */
    public function getPayment(string $fundRequestId, string $paymentId): array
    {
        return $this->client->call('GET', "owo/v1/fund-requests/{$fundRequestId}/payments/{$paymentId}");
    }
}
