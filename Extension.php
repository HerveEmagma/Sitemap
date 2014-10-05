<?php
// Sitemap Extension for Bolt, by Bob den Otter

namespace Bolt\Extension\Bolt\Sitemap;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Extensions\Snippets\Location as SnippetLocation;

class Extension extends \Bolt\BaseExtension
{
    public function getName()
    {
        return "Sitemap";
    }

    /**
     * Initialize Sitemap. Called during bootstrap phase.
     */
    function initialize()
    {
        if (empty($this->config['ignore_contenttype'])) {
            $this->config['ignore_contenttype'] = array();
        }

        // Set up the routes for the sitemap..
        $this->app->match("/sitemap", array($this, 'sitemap'));
        $this->app->match("/sitemap.xml", array($this, 'sitemapXml'));

        $this->insertSnippet(SnippetLocation::END_OF_HEAD, 'headsnippet');

    }

    public function sitemap($xml = false)
    {
        if($xml){
            $this->app['extensions']->clearSnippetQueue();
            $this->app['extensions']->disableJquery();
            $this->app['debugbar'] = false;
        }

        $links = array(array('link' => $this->app['paths']['root'], 'title' => $this->app['config']->get('general/sitename')));
        foreach( $this->app['config']->get('contenttypes') as $contenttype ) {
            if(!in_array($contenttype['slug'], $this->config['ignore_contenttype'])) {
                if (isset($contenttype['listing_template'])) {
                    $links[] = array( 'link' => $this->app['paths']['root'].$contenttype['slug'], 'title' => $contenttype['name'] );
                }
                $content = $this->app['storage']->getContent(
                    $contenttype['slug'],
                    array('limit' => 10000, 'order' => 'datepublish desc')
                );
                foreach( $content as $entry ) {
                    $links[] = array('link' => $entry->link(), 'title' => $entry->getTitle(),
                        'lastmod' => date( \DateTime::W3C, strtotime($entry->get('datechanged'))));
                }
            }
        }

        foreach($links as $idx => $link) {
            if(in_array($link['link'], $this->config['ignore'])) {
                unset($links[$idx]);
            }
        }

        if ($xml) {
            $template = $this->config['xml_template'];
        } else {
            $template = $this->config['template'];
        }

        $this->app['twig.loader.filesystem']->addPath(__DIR__);

        $body = $this->app['render']->render($template, array(
            'entries' => $links
        ));

        $headers = array();
        if ($xml) {
            $headers['Content-Type'] = 'application/xml; charset=utf-8';
        }

        return new Response($body, 200, $headers);

    }

    public function sitemapXml()
    {
        return $this->sitemap(true);
    }

    public function headsnippet()
    {

        $snippet = sprintf(
            '<link rel="sitemap" type="application/xml" title="Sitemap" href="%ssitemap.xml">',
            $this->app['paths']['rooturl']
        );

        return $snippet;

    }


}

