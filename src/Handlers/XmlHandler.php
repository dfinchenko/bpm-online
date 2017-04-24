<?php
namespace agoalofalife\bpm\Handlers;

use agoalofalife\bpm\Contracts\Collection;
use agoalofalife\bpm\Contracts\Handler;

/**
 * Class XmlHandler
 * @property string buildXml
 * @package agoalofalife\bpm\Handlers
 */
class XmlHandler implements Handler, Collection
{
    use XmlConverter;

    private $response;

    private $validText = [];

    private $buildXml;

    /*
    |--------------------------------------------------------------------------
    | Namespaces XML API BPM
    |--------------------------------------------------------------------------
    | Namespaces in BPM API to parse the XML response
    |
    */
    private $namespaces = [
        'NamespaceAtom'         => 'http://www.w3.org/2005/Atom',
        'NamespaceMetadata'     => 'http://schemas.microsoft.com/ado/2007/08/dataservices/metadata',
        'NamespaceDataServices' => 'http://schemas.microsoft.com/ado/2007/08/dataservices',
    ];

    /*
    |--------------------------------------------------------------------------
    | List Namespaces for post request in BPM
    |--------------------------------------------------------------------------
    |   Namespaces To specify a file in XML
    |
    */
    private $listNamespaces = [
            ['xml:base'        => 'http://softex-iis:7503/0/ServiceModel/EntityDataService.svc/'],
            ['xmlns'           => 'http://www.w3.org/2005/Atom'],
            ['xmlns:d'         => 'http://schemas.microsoft.com/ado/2007/08/dataservices'],
            ['xmlns:m'         => 'http://schemas.microsoft.com/ado/2007/08/dataservices/metadata'],
            ['xmlns:georss'    => 'http://www.georss.org/georss'],
            ['xmlns:gml'       => 'http://www.opengis.net/gml'],
    ];

    /*
    |--------------------------------------------------------------------------
    |   Prefix XML document
    |--------------------------------------------------------------------------
    |   The prefix for insertion into Namespace XML document to be sent to API BPM
    |
    */
    private $prefixNamespace = 'd';

    public function getAccept()
    {
        return '';
    }

    public function getContentType()
    {
        return 'application/atom+xml;type=entry';
    }

    public function parse($response)
    {
        $this->response      = simplexml_load_string($response);
        $copyXml             = $this->response;

        if ( $this->checkIntegrity($this->response) === false )
        {
            return [];
        }

            $array_vars_list    = get_object_vars($copyXml);

            if (key_exists('content', $array_vars_list)) {
                return $this->arrayOne();
            }
            if (key_exists('workspace', $array_vars_list)) {
                return  $this->workspace();
            } else {
                return $this->arrayMany();
            }
    }

    public function checkIntegrity($response)
    {
        if ( empty($response->message) )
        {
            return true;
        }
        return false;
    }

    public function getData()
    {
        return $this->validText;
    }

    public function toArray()
    {
       return  $this->xmlToArrayRecursive($this->validText);
    }

    public function toArrayCollect()
    {
        return  collect($this->xmlToArrayRecursive($this->validText));
    }

    public function toJson()
    {
        return  json_encode($this->xmlToArrayRecursive($this->validText));
    }

    /**
     * Return All Collection Bpm
     * if not specified all parameters in url
     * return list all collection from bpm
     * @throws \Exception
     */
    private function workspace()
    {
        if ( !empty($this->response->message->collection->title) ) {
            throw new \Exception("responce BPM API : ".
                $this->response->innererror->message.". ENG :".  $this->response->message);
        }
        foreach ($this->response->workspace->collection as $item) {
            $this->validText[] = get_object_vars($item->children(  $this->namespaces['NamespaceAtom'] ))['title'];
        }
        return $this;
    }

    /**
     * Extraction array in response XML , more element one
     * @return array
     * @throws \ExceptionF
     */
    private function arrayMany()
    {
        try {
            foreach ($this->response->children( $this->namespaces['NamespaceAtom'] )->entry as $item ) {
                $this->validText[] =   $item->content->children( $this->namespaces['NamespaceMetadata'] )
                    ->children($this->namespaces['NamespaceDataServices']);
            }

            return $this;
        } catch (\Exception $e) {
            dd($this->responceXML);
        }
    }
    /**
     *  Get one Element
     * @return mixed
     */
    private function arrayOne()
    {
        $this->validText = $this->response->children( $this->namespaces['NamespaceAtom'] )->content
            ->children( $this->namespaces['NamespaceMetadata'] )
            ->children( $this->namespaces['NamespaceDataServices'] );
        return $this;
    }


    /**
     * Xml text for request in Bpm Online
     * @param $data
     * @return string
     */
    public function create($data)
    {

        //----------  Base  ----------//
        $dom          = new \DOMDocument('1.0', 'utf-8');
        $entry        = $dom->createElement('entry');
        $dom->appendChild($entry);

        //----------  NameSpaces  ----------//
        foreach ($this->listNamespaces as $key => $value) {

            $xmlBase  = $dom->createAttribute(key($value));
            $entry->appendChild($xmlBase);

            $value    = $dom->createTextNode($value[key($value)]);
            $xmlBase->appendChild($value);
        }

        //----------  <content type="application/xml">  ----------//
        $content      = $dom->createElement('content');
        $entry->appendChild($content);
        $xmlns_dcd    = $dom->createAttribute('type');
        $content->appendChild($xmlns_dcd);
        $valued       = $dom->createTextNode('application/xml');
        $xmlns_dcd->appendChild($valued);

        //----------  properties  ----------//
        $properties   = $dom->createElement('m:properties');
        $content->appendChild($properties);

        foreach ($data as $nameField => $valueField) {
            $element  = $dom->createElement($this->prefixNamespace.':'.$nameField);
            $properties->appendChild($element);
            $valued   = $dom->createTextNode($valueField);
            $element->appendChild($valued);
        }
        return $this->buildXml = $dom->saveXML();
    }
}