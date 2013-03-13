<?php
/**
 * This file is part of the Bepado Common Component.
 *
 * @version 1.0.0snapshot201303061109
 */

namespace Bepado\Common\Rpc\Marshaller\CallUnmarshaller;

use Bepado\Common\Rpc\Marshaller\CallUnmarshaller;
use Bepado\Common\Rpc;
use Bepado\Common\Rpc\Marshaller\Converter\NoopConverter;
use Bepado\Common\Rpc\Marshaller\Converter;
use Bepado\Common\Rpc\Marshaller\ValueUnmarshaller\XmlValueUnmarshaller;
use Bepado\Common\Struct\RpcCall;

class XmlCallUnmarshaller extends CallUnmarshaller
{
    /**
     * @var \DOMDocument
     */
    private $document;

    /**
     * @var \Bepado\Common\Rpc\Marshaller\ValueUnmarshaller\XmlValueUnmarshaller
     */
    private $valueUnmarshaller;

    /**
     * @param Converter|null $converter
     */
    public function __construct(Converter $converter = null)
    {
        $this->converter = $converter ?: new NoopConverter();
        $this->valueUnmarshaller = new XmlValueUnmarshaller($this->converter);
    }

    /**
     * @param string $data
     * @return \Bepado\Common\Struct\RpcCall
     */
    public function unmarshal($data)
    {
        $this->document = $this->loadXml($data);
        return $this->unmarshalRpcCall(
            $this->document->documentElement
        );
    }

    private function unmarshalRpcCall(\DOMElement $element)
    {
        $rpcCall = new RpcCall();

        foreach ($element->childNodes as $child) {
            /** @var \DOMElement $child */
            switch($child->localName) {
                case "service":
                    $rpcCall->service = $child->textContent;
                    break;
                case "command":
                    $rpcCall->command = $child->textContent;
                    break;
                case "arguments":
                    foreach ($child->childNodes as $argument) {
                        /** @var \DOMElement $argument */
                        $rpcCall->arguments[] = $this->valueUnmarshaller->unmarshal($argument);
                    }
                    break;
                default:
                    throw new \RuntimeException("Unknown XML element: {$child->localName}.");
            }
        }

        return $rpcCall;
    }

    /**
     * @param string $data
     * @return \DOMDocument
     * @throws \UnexpectedValueException
     */
    private function loadXml($data)
    {
        $oldErrorState = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($data);
        $document->normalizeDocument();

        $errors = libxml_get_errors();
        libxml_use_internal_errors($oldErrorState);

        if (count($errors) > 0) {
            throw new \UnexpectedValueException(
                "The provided RPC XML is invalid: {$errors[0]->message}.'{$data}'" 
            );
        }

        return $document;
    }
}
