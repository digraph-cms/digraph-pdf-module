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
        //split content at pdf-processing-split comments and write it into the
        //pdf in chunks. Routes should use this to avoid running into the
        //pcre.backtrack_limit limitation in mpdf
        $content = explode('<!--pdf-processing-split-->', $package['response.content']);
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
        $config = $this->cms->helper('pdf')->config($package->noun());
        if (!$config['enabled']) {
            return false;
        }
        $package->merge(
            $this->cms->helper('pdf')->templateFields($package->noun()),
            'fields'
        );
        return true;
    }
}
