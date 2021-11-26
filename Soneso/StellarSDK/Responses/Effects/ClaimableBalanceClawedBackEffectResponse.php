<?php  declare(strict_types=1);

// Copyright 2021 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace Soneso\StellarSDK\Responses\Effects;

use Soneso\StellarSDK\Asset;

class ClaimableBalanceClawedBackEffectResponse extends EffectResponse
{
    private string $balanceId;

    /**
     * @return string
     */
    public function getBalanceId(): string
    {
        return $this->balanceId;
    }

    protected function loadFromJson(array $json) : void {
        if (isset($json['balance_id'])) $this->balanceId = $json['balance_id'];
        parent::loadFromJson($json);
    }

    public static function fromJson(array $jsonData) : ClaimableBalanceClawedBackEffectResponse {
        $result = new ClaimableBalanceClawedBackEffectResponse();
        $result->loadFromJson($jsonData);
        return $result;
    }
}