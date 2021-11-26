<?php declare(strict_types=1);

// Copyright 2021 The Stellar PHP SDK Authors. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.


namespace Soneso\StellarSDK;

use Exception;
use InvalidArgumentException;
use phpseclib3\Math\BigInteger;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Util\Hash;
use Soneso\StellarSDK\Xdr\XdrEncoder;
use Soneso\StellarSDK\Xdr\XdrEnvelopeType;
use Soneso\StellarSDK\Xdr\XdrSequenceNumber;
use Soneso\StellarSDK\Xdr\XdrTransaction;
use Soneso\StellarSDK\Xdr\XdrTransactionEnvelope;
use Soneso\StellarSDK\Xdr\XdrTransactionV0Envelope;
use Soneso\StellarSDK\Xdr\XdrTransactionV1Envelope;

class Transaction extends AbstractTransaction
{
    private int $fee = AbstractTransaction::MIN_BASE_FEE;
    private BigInteger $sequenceNumber;
    private MuxedAccount $sourceAccount;
    private array $operations; //[AbstractOperation]
    private Memo $memo;
    private ?TimeBounds $timeBounds;

    public function __construct(MuxedAccount $sourceAccount, BigInteger $sequenceNumber, array $operations,
                                ?Memo $memo = null, ?TimeBounds $timeBounds = null,
                                ?int $fee = null) {

        if (count($operations) == 0) {
            throw new InvalidArgumentException("At least one operation required");
        }

        foreach ($operations as $operation) {
            if (!($operation instanceof AbstractOperation)) {
                throw new InvalidArgumentException("operation array contains unknown operation type");
            }
        }

        if ($fee == null) {
            $this->fee = AbstractTransaction::MIN_BASE_FEE * count($operations);
        } else {
            $this->fee = $fee;
        }

        $this->sourceAccount = $sourceAccount;
        $this->sequenceNumber = $sequenceNumber;
        $this->operations = $operations;
        $this->timeBounds = $timeBounds;
        $this->memo = $memo ?? Memo::none();
        parent::__construct();
    }

    /**
     * @return BigInteger
     */
    public function getSequenceNumber(): BigInteger
    {
        return $this->sequenceNumber;
    }

    /**
     * @return int
     */
    public function getFee(): int
    {
        return $this->fee;
    }

    /**
     * @return MuxedAccount
     */
    public function getSourceAccount(): MuxedAccount
    {
        return $this->sourceAccount;
    }

    /**
     * @return array
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * @return Memo
     */
    public function getMemo(): Memo
    {
        return $this->memo;
    }

    /**
     * @return TimeBounds|null
     */
    public function getTimeBounds(): ?TimeBounds
    {
        return $this->timeBounds;
    }

    public function signatureBase(Network $network): string
    {
        $bytes = Hash::generate($network->getNetworkPassphrase());
        $bytes .= XdrEncoder::unsignedInteger32(XdrEnvelopeType::ENVELOPE_TYPE_TX);
        $bytes .= $this->toXdr()->encode();
        return $bytes;
    }

    public function toXdr() : XdrTransaction {
        $xdrMuxedSourceAccount = $this->sourceAccount->toXdr();
        $xdrSequenceNr = new XdrSequenceNumber($this->sequenceNumber);
        $xdrOperations = array();
        foreach ($this->operations as $operation) {
            if ($operation instanceof AbstractOperation) {
                array_push($xdrOperations, $operation->toXdr());
            }
        }
        $xdrMemo = $this->memo->toXdr();
        $xdrTimeBounds = $this->timeBounds?->toXdr();
        return new XdrTransaction($xdrMuxedSourceAccount, $xdrSequenceNr, $xdrOperations, $this->fee, $xdrMemo, $xdrTimeBounds);
    }

    public function toXdrBase64() : string {
        $xdr = $this->toXdr();
        return base64_encode($xdr->encode());
    }
    /**
     * @throws Exception if transaction is not signed.
     */
    public function toEnvelopeXdr(): XdrTransactionEnvelope
    {
        if (count($this->getSignatures()) == 0) {
            throw new Exception("Transaction must be signed by at least one signer. Use transaction.sign().");
        }
        $xdrTransaction = $this->toXdr();
        $v1Envelope = new XdrTransactionV1Envelope($xdrTransaction, $this->getSignatures());
        $type = new XdrEnvelopeType(XdrEnvelopeType::ENVELOPE_TYPE_TX);
        $xdrEnvelope = new XdrTransactionEnvelope($type);
        $xdrEnvelope->setV1($v1Envelope);
        return $xdrEnvelope;
    }

    public static function fromV1EnvelopeXdr(XdrTransactionV1Envelope $envelope) : Transaction {
        $tx = $envelope->getTx();
        $sourceAccount = MuxedAccount::fromXdr($tx->getSourceAccount());
        $fee = $tx->getFee();
        $seqNr = $tx->getSequenceNumber()->getValue();
        $memo = Memo::fromXdr($tx->getMemo());
        $operations = array();
        $timeBounds = null;
        if ($tx->getTimeBounds() != null) {
            $timeBounds = TimeBounds::fromXdr($tx->getTimeBounds());
        }
        foreach($tx->getOperations() as $operation) {
            array_push($operations, AbstractOperation::fromXdr($operation));
        }
        return new Transaction($sourceAccount, $seqNr, $operations, $memo, $timeBounds, $fee);
    }

    public static function fromV0EnvelopeXdr(XdrTransactionV0Envelope $envelope) : Transaction {
        $tx = $envelope->getTx();
        $accId = KeyPair::fromPublicKey($tx->getSourceAccountEd25519())->getAccountId();
        $sourceAccount = MuxedAccount::fromAccountId($accId);
        $fee = $tx->getFee();
        $seqNr = $tx->getSequenceNumber()->getValue();
        $memo = Memo::fromXdr($tx->getMemo());
        $operations = array();
        $timeBounds = null;
        if ($tx->getTimeBounds() != null) {
            $timeBounds = TimeBounds::fromXdr($tx->getTimeBounds());
        }
        foreach($tx->getOperations() as $operation) {
            array_push($operations, AbstractOperation::fromXdr($operation));
        }
        return new Transaction($sourceAccount, $seqNr, $operations, $memo, $timeBounds, $fee);
    }

    public static function builder(TransactionBuilderAccount $sourceAccount) : TransactionBuilder{
        return new TransactionBuilder($sourceAccount);
    }
}