<?php

namespace SamIT\Streams;


trait XmlParserTrait
{

    abstract protected function add(array $map, array $record, int $lineNr = null, $raw = '');
    abstract protected function normalize($value, $type, $row);
    abstract protected function log(string $text);

    /**
     * Parses an XML node
     * @param \DOMElement $node
     * @param string $mapName
     * @return array Returns an associative array.
     */
    protected function parseXmlNode(\DOMElement $node, $map) {
        /** @var \DOMElement $attribute */
        $attribute = $node->firstChild;
        $result = [];
        do {
            $name = $attribute->localName;
            if (isset($map['sourceMap'][$name])) {
                foreach($map['sourceMap'][$name] as $target) {
                    //pass null since we do not have implemented $row yes to pass to normalize
                    $value = $this->normalize($attribute->nodeValue, $map['map'][$target]['type'], $node);

                    if (isset($result[$target])) {
                        switch ($map['map'][$target]['multiple']) {
                            case 'array': if (is_array($result[$target])) {
                                    $result[$target][] = $value;
                                } else {
                                    $result[$target] = [$result[$target], $value];
                                }
                                break;
                            case 'last':
                                $result[$target] = $value;
                                break;
                            default:
                                throw new \Exception("Unknown value for multiple: {$map['map'][$target]['multiple']}");

                        }
                    } else {
                        $result[$target] = $value;
                    }
                }
            }
        } while(null !== $attribute = $attribute->nextSibling);
        foreach($map['map'] as $target => $def) {
            if (!isset($result[$target])) {
                $result[$target] = null;
            }
        }
        return $result;
    }


    protected function importXmlFile($fileName, $stream, array $map) {
        if (isset($map['xmlNode'])) {
            $doc = new \DOMDocument();
            $doc->loadXML(stream_get_contents($stream));
            while (null !== $node = $doc->getElementsByTagName($map['xmlNode'])->item(0)) {
                $record = $this->parseXmlNode($node, $map);
                $this->add($map, $record, 0, $node);
                $node->parentNode->removeChild($node);
            }
        }
    }
}