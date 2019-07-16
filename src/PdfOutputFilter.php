<?php
namespace Digraph\Modules\digraph_pdf;

use Mpdf\Mpdf;
use Flatrr\SelfReferencingFlatArray;

class PdfOutputFilter extends \Digraph\OutputFilters\AbstractOutputFilter
{
    public function doFilterPackage(&$package)
    {
        $config = $this->cms->helper('pdf')->config($package->noun());
        $package->merge($config->get(null, true), 'pdf');
        $mpdf = $this->cms->helper('pdf')->mpdf($package->noun());
        //write CSS
        $mpdf->WriteHTML(
            $this->cms->helper('media')->getContent('digraph-pdf.css'),
            1
        );
        //pull content from package
        $content = $package['response.content'];
        //break out URLs from a[href] tags so they'll print
        if ($config['print_link_urls']) {
            $baseurl = $this->cms->config['url.base'];
            $content = preg_replace_callback(
                '/(<a.*? href=([\'"])(.+?)\2.*?>)(.+?)(<\/a>)/i',
                function ($matches) use ($baseurl) {
                    $full = $matches[0];
                    $url = $matches[3];
                    $left = $matches[1];
                    $text = $matches[4];
                    $right = $matches[5];
                    if (filter_var($url, \FILTER_VALIDATE_URL) !== false) {
                        if (strpos($url, $baseurl) === 0) {
                            $url = '/'.substr($url, strlen($baseurl));
                        }
                        return "$left$text<span class=\"digraph-pdf-url\"> ($url)</span>$right";
                    }
                    return $full;
                },
                $content
            );
        }
        //split content at pdf-processing-split comments and write it into the
        //pdf in chunks. Routes should use this to avoid running into the
        //pcre.backtrack_limit limitation in mpdf
        $content = explode('<!--pdf-processing-split-->', $content);
        foreach ($content as $chunk) {
            try {
                $mpdf->WriteHTML($chunk, 2);
            } catch (\Exception $e) {
                $package->error(500, 'A piece of HTML failed to write into the PDF');
                return;
            }
        }
        //set up package metadata
        $package['pdf.filename.date'] = date('Ymd');
        $package['pdf.filename.contenthash'] = substr(md5($package['response.content']), 0, 8);
        $filename = $package['pdf.filename.prefix'];
        $filename .= $package['pdf.filename.name'];
        $filename .= $package['pdf.filename.suffix'];
        $filename = preg_replace('/[^a-z0-9\-_]+/i', '-', $filename);
        $filename .= '.pdf';
        $package->merge($config['package'], null, true);
        $package->makeMediaFile($filename);
        $package->binaryContent($mpdf->output('', 'S'));
    }

    public function doPreFilterPackage(&$package)
    {
        $package->merge(
            $this->cms->helper('pdf')->templateFields($package->noun()),
            'fields'
        );
        return true;
    }
}
