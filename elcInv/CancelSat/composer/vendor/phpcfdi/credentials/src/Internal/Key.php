<?php

namespace PhpCfdi\Credentials\Internal;

/** @internal  */
class Key
{
    use DataArrayTrait;

    /** @var OpenSslKeyTypeEnum|null */
    private $type;

    /** @param array<mixed> $dataArray */
    public function __construct($dataArray)
    {
        $this->dataArray = $dataArray;
    }

    /** @return array<mixed> */
    public function parsed()
    {
        return $this->dataArray;
    }

    public function publicKeyContents()
    {
        return $this->extractString('key');
    }

    public function numberOfBits()
    {
        return $this->extractInteger('bits');
    }

    public function type()
    {
        if (null === $this->type) {
            $this->type = new OpenSslKeyTypeEnum($this->extractInteger('type'));
        }
        return $this->type;
    }

    /** @return array<mixed> */
    public function typeData()
    {
        return $this->extractArray($this->type()->value());
    }

    /**
     * @param int $type one of OPENSSL_KEYTYPE_RSA, OPENSSL_KEYTYPE_DSA, OPENSSL_KEYTYPE_DH, OPENSSL_KEYTYPE_EC
     * @return bool
     */
    public function isType($type)
    {
        return ($this->type()->index() === $type);
    }
}
