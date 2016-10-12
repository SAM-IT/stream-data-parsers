<?php

namespace SamIT\Streams;


trait XmlParserTrait
{

    abstract protected function add(array $map, $record, $lineNr = null, $line = '');

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
                    if (isset($result[$target]) && $map['map'][$target]['type'] == 'string') {
                        $result[$target] .= ',' . $this->normalize($attribute->nodeValue, $map['map'][$target]['type'], $node);
                    } else {
                        $result[$target] = $this->normalize($attribute->nodeValue, $map['map'][$target]['type'], $node);
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
            $base = basename($fileName);
            $node = $doc->getElementsByTagName($map['xmlNode'])->item(0);
            do {
                $record = $this->parseXmlNode($node, $map);
                $this->add($map, $record, 0, $node);
            } while(null !== $node = $node->nextSibling);
        }

    }
}