<?php
/**
 * Load update chunk page
 *
 * @package modx
 * @subpackage manager.element.chunk
 */
class ElementChunkUpdateManagerController extends modManagerController {
    public $onChunkFormRender = '';
    public $chunk;
    public $chunkArray = array();
    
    /**
     * Check for any permissions or requirements to load page
     * @return bool
     */
    public function checkPermissions() {
        return $this->modx->hasPermission('edit_chunk');
    }

    /**
     * Register custom CSS/JS for the page
     * @return void
     */
    public function loadCustomCssJs() {
        $mgrUrl = $this->modx->getOption('manager_url',null,MODX_MANAGER_URL);
        $this->modx->regClientStartupScript($mgrUrl.'assets/modext/widgets/core/modx.grid.local.property.js');
        $this->modx->regClientStartupScript($mgrUrl.'assets/modext/widgets/element/modx.grid.element.properties.js');
        $this->modx->regClientStartupScript($mgrUrl.'assets/modext/widgets/element/modx.panel.chunk.js');
        $this->modx->regClientStartupScript($mgrUrl.'assets/modext/sections/element/chunk/update.js');
        $this->modx->regClientStartupHTMLBlock('<script type="text/javascript">
        // <![CDATA[
        Ext.onReady(function() {
            MODx.load({
                xtype: "modx-page-chunk-update"
                ,chunk: "'.$this->chunkArray['id'].'"
                ,record: '.$this->modx->toJSON($this->chunkArray).'
            });
        });
        MODx.onChunkFormRender = "'.$this->onChunkFormRender.'";
        MODx.perm.unlock_element_properties = '.($this->modx->hasPermission('unlock_element_properties') ? 1 : 0).';
        // ]]>
        </script>');

    }

    /**
     * Custom logic code here for setting placeholders, etc
     * @param array $scriptProperties
     * @return mixed
     */
    public function process(array $scriptProperties = array()) {
        $placeholders = array();
    
        /* grab chunk */
        if (empty($scriptProperties['id'])) return $this->failure($this->modx->lexicon('chunk_err_ns'));
        $this->chunk = $this->modx->getObject('modChunk',$scriptProperties['id']);
        if (empty($this->chunk)) return $this->failure($this->modx->lexicon('chunk_err_nfs',array('id' => $scriptProperties['id'])));
        if (!$this->chunk->checkPolicy('view')) return $this->failure($this->modx->lexicon('access_denied'));

        if ($this->chunk->get('locked') && !$this->modx->hasPermission('edit_locked')) {
            return $this->failure($this->modx->lexicon('chunk_err_locked'));
        }

        /* grab category for chunk, assign to parser */
        $placeholders['chunk'] = $this->chunk;

        /* invoke OnChunkFormRender event */
        $placeholders['onChunkFormRender'] = $this->fireRenderEvent();

        /* get properties */
        $properties = $this->chunk->get('properties');
        if (!is_array($properties)) $properties = array();

        $data = array();
        foreach ($properties as $property) {
            $data[] = array(
                $property['name'],
                $property['desc'],
                $property['type'],
                $property['options'],
                $property['value'],
                $property['lexicon'],
                false, /* overridden set to false */
                $property['desc_trans'],
            );
        }
        $this->chunkArray = $this->chunk->toArray();
        $this->chunkArray['properties'] = $data;

        /* invoke OnRichTextEditorInit event */
        $placeholders['onRTEInit'] = $this->loadRte();

        /* check unlock default element properties permission */
        $placeholders['unlock_element_properties'] = $this->modx->hasPermission('unlock_element_properties') ? 1 : 0;
 
        return $placeholders;
    }

    public function firePostRenderEvents() {
        /* PreRender events inject directly into the HTML, as opposed to the JS-based Render event which injects HTML
        into the panel */
        $this->firePrerenderEvent();
    }

    /**
     * Invoke OnRichTextEditorInit event, loading the RTE
     * @return string
     */
    public function loadRte() {
        $o = '';
        if ($this->modx->getOption('use_editor') == 1) {
            $onRTEInit = $this->modx->invokeEvent('OnRichTextEditorInit',array(
                'elements' => array('post'),
                'chunk' => &$this->chunk,
                'mode' => modSystemEvent::MODE_UPD,
            ));
            if (is_array($onRTEInit)) {
                $onRTEInit = implode('', $onRTEInit);
            }
            $o = $onRTEInit;
        }
        return $o;
    }

    /**
     * Fire the OnChunkFormPrerender event
     * @return mixed
     */
    public function firePreRenderEvent() {
        $o = $this->modx->invokeEvent('OnChunkFormPrerender',array(
            'id' => $this->chunk->get('id'),
            'mode' => modSystemEvent::MODE_UPD,
            'chunk' => $this->chunk,
        ));
        if (is_array($o)) { $o = implode('',$o); }
        return $o;
    }

    /**
     * Fire the OnChunkFormRender event
     * @return mixed
     */
    public function fireRenderEvent() {
        $this->onChunkFormRender = $this->modx->invokeEvent('OnChunkFormRender',array(
            'id' => $this->chunk->get('id'),
            'mode' => modSystemEvent::MODE_UPD,
            'chunk' => $this->chunk,
        ));
        if (is_array($this->onChunkFormRender)) $this->onChunkFormRender = implode('', $this->onChunkFormRender);
        $this->onChunkFormRender = str_replace(array('"',"\n","\r"),array('\"','',''),$this->onChunkFormRender);
        return $this->onChunkFormRender;
    }


    /**
     * Return the pagetitle
     *
     * @return string
     */
    public function getPageTitle() {
        return $this->modx->lexicon('chunk').': '.$this->chunk->get('name');
    }

    /**
     * Return the location of the template file
     * @return string
     */
    public function getTemplateFile() {
        return 'element/chunk/update.tpl';
    }

    /**
     * Specify the language topics to load
     * @return array
     */
    public function getLanguageTopics() {
        return array('chunk','category','propertyset','element');
    }
}