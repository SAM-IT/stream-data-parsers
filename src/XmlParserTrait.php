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
     * @param $map
     * @return array Returns an associative array.
     * @throws \Exception
     * @internal param string $mapName
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
            $reader = new \XMLReader();
            $reader->open(StreamProxy::to_uri($stream));

            $this->findNode($reader, $map['xmlNode']);

            do {
                if (
                    $reader->nodeType == \XMLReader::END_ELEMENT
                    || (isset($map['dataNode']) && !$this->findNode($reader, $map['dataNode'], $map['dataPath'] ?? null))
                ) {
                    continue;
                }

                $node = $reader->expand();
                $this->add($map, $this->parseXmlNode($node, $map), 0, $node);
            } while ($reader->next($map['xmlNode']));
        }
    }

    private function findNode(\XMLReader $reader, string $localName, array $path = null)
    {
        $currentPath = [];
        do {
            $reader->read();

            switch ($reader->nodeType) {
                case \XMLReader::ELEMENT:
                    if ($reader->localName === $localName && ($path === null || $currentPath === $path)) {
                        return true;
                    }
                    array_push($currentPath, $reader->localName);
                    break;

                case \XMLReader::END_ELEMENT:
                    if (empty($currentPath)) {
                        return false;
                    }
                    array_pop($currentPath);
                    break;
            }

        } while ($reader->nodeType != \XMLReader::NONE);
        
        return false;
    }
}
