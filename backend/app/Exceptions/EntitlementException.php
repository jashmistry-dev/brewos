<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class EntitlementException extends Exception
{
    public function __construct(
        string $message = 'Entitlement limit reached or feature not available on your current subscription plan.',
        protected string $featureKey = 'general',
        protected ?int $limit = null,
        protected ?int $currentUsage = null,
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    public function getFeatureKey(): string
    {
        return $this->featureKey;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getCurrentUsage(): ?int
    {
        return $this->currentUsage;
    }

    public function render(): JsonResponse
    {
        $fieldKey = match ($this->featureKey) {
            'staff_limit'     => 'staff',
            'table_limit'     => 'table',
            'branch_limit'    => 'branch',
            'menu_item_limit' => 'menu_item',
            default           => $this->featureKey,
        };

        return response()->json([
            'message'       => $this->getMessage(),
            'feature'       => $this->featureKey,
            'limit'         => $this->limit,
            'current_usage' => $this->currentUsage,
            'error_code'    => 'ENTITLEMENT_LIMIT_REACHED',
            'errors'        => [
                $fieldKey => [$this->getMessage()],
            ],
        ], 422);
    }
}
