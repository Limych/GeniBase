<?php
namespace GeniBase\Rs\Server;

use Gedcomx\Gedcomx;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Rt\GedcomxModelVisitorBase;
use Gedcomx\Common\TextValue;
use Gedcomx\Util\FormalDate;

/**
 *
 * @author Limych
 *        
 */
class GedcomxRsUpdater extends GedcomxModelVisitorBase
{
    
    public static function update(Gedcomx $document)
    {
        $visitor = new self();
        $document->accept($visitor);
        return $document;
    }
    
    /**
     * Visits the place description.
     *
     * @param \Gedcomx\Conclusion\PlaceDescription $place
     */
    public function visitPlaceDescription(PlaceDescription $place)
    {
        /** @var DateInfo $r */
        if (! empty($r = $place->getTemporalDescription())) {
            array_push($this->contextStack, $place);
            $r->accept($this);
            array_pop($this->contextStack);
        }
        
        parent::visitPlaceDescription($place);
    }
    
    public function visitDate(DateInfo $date)
    {
        $tv = new TextValue([
            'lang'  => 'ru'
        ]);
        
        if (! empty($d = $date->getFormal())) {
            $fd = new FormalDate();
            $fd->parse($d);
            
            $r = '';
            if (! empty($x = $fd->getStart())) {
                $r .= $x->getYear();
            } else {
                $r .= 'N/A';
            }
            if (! empty($x = $fd->getEnd())) {
                $r .= '–' . $x->getYear();
            } elseif (! empty($r)) {
                $r .= '–N/A';
            }
            
            $tv->setValue($r);
        } else {
            $tv->setValue($date->getOriginal());
        }
        
        $date->setNormalizedExtensions([$tv]);
        
        parent::visitDate($date);
    }
    
}
