<?php

class ProcessLanguages extends Process {
    
/******
 ***** Change before installation
 *****/    
    var $table_name = "module_languages";
    var $template_home = "home_language";
    var $translation_prefix = "[Translation] ";
/******
 ***** Don’t change
 *****/    
    var $hooks = array(
        "setLanguage" => null,
        "addLanguageManagement" => null,
        "markNewPage" => null,        
        "pagemapNewPage" => null
        );

    public static function getModuleInfo() {
        return array(
            'title' => 'Languages',
            'summary' => 'Manage multiple languages.',
            'href' => 'http://processwire.com/talk/index.php/topic,414.0.html',
            'version' => 007,
            'permanent' => false,
            'autoload' => true,
            'singular' => true
        );
    }


/******
 ***** Initialisation
 ***** of class and template var
 *****/
    /**
     * Module init
     *
     */
    public function init() {
        parent::init();

        // sets hook for adding language tab to page edit form
        $this->hooks['addLanguageManagement'] = $this->addHookAfter("ProcessPageEdit::buildForm", $this, 'addLanguageManagement');
        
        // sets hook for creating a pagemap entry on creation of a new subpage of any language page tree
        $this->hooks['markNewPage'] = $this->pages->addHookBefore("save", $this, 'markNewPage');
        $this->hooks['pagemapNewPage'] = $this->pages->addHookAfter("save", $this, 'pagemapSavedPage');
        
        // sets hook for deleting a pagemap entry on deletion of a new subpage of any language page tree
        //$this->hooks['unpagemapPageOnDelete'] = $this->pages->addHookAfter("delete", $this, 'unpagemapPage');

        // sets hook for deleting a pagemap entry on deletion of a new subpage of any language page tree
        // $this->hooks['unpagemapPageBeforeEmptyTrash'] = $this->pages->addHookBefore("ProcessPageTrash::execute", $this, 'togglePageOnDelete');
        // $this->hooks['unpagemapPageAfterEmptyTrash'] = $this->pages->addHookAfter("ProcessPageTrash::execute", $this, 'togglePageOnDelete');     
        
        // sets hook for catching calls on '/' and leading them to default language’s home page
        $this->hooks['setLanguage'] = $this->addHookBefore('Page::render', $this, 'setLanguage');
    
    }
    
    /**
     * set template var
     *
     */ 
    private function _setTemplateVar($page) {
        if((is_object($page)) && ($lang_id = $this->hasLanguage($page)) && ($lang = $this->getLanguage($lang_id))) {
            $var = new stdClass();
            $var->current = new stdClass();
            $var->current->id = $lang['id'];
            $var->current->language = $lang['language'];
            $var->current->code = $lang['code'];
            $var->current->home = $this->pages->get($lang['home']);
            $var->translations = $this->getTranslations($page, true, true); 
            return $var; 
        } else {
            return false;
        }       
    }

/******
 ***** Admin interface
 *****
 *****/

    /**
     * Module execution
     *
     */
    public function ___execute() {
        $this->setFuel('processHeadline', 'Languages');

        $description = "";

        $table = $this->modules->get("MarkupAdminDataTable");
        $table->setEncodeEntities(false);
        $table->headerRow(array('Language', 'Code', 'Home', 'Default', 'Sync', 'Delete'));

        $result = $this->db->query("SELECT * FROM {$this->table_name} ORDER BY language");

        while($row = $result->fetch_assoc()) {

            $row['home'] = $this->_getPath($row['home']); 

             // output in table rows with edit link and delete checkbox?
             $table->row(array(
                 $row['language'] => "edit/?id=$row[id]",
                 $row['code'] => "edit/?id=$row[id]",
                 $row['home'],
                 ($row['default'])?"yes":"no",
                 ($row['sync'])?"yes":"no",
                 "<input type='checkbox' name='delete[{$row['id']}]' value='1'".($row['default']?" disabled='disabled'":"")." />"
                 ));
        }

        $button = $this->modules->get("InputfieldButton");
        $button->type = 'submit';
        $button->id = 'submit_delete';
        $button->value = 'Remove selected languages';

        $table->action(array('Add language' => 'edit/?id=0'));

        // Is there clean way to add button to right side?
        return $description . "<form id='languages_form' action='./delete/' method='post'>" .$table->render() . $button->render() . "</form>";
    }
    
    
    /**
     * Edit/Add language - Called when the URL is: ./edit/
     *
     */
    public function ___executeEdit() {

        $this->fuel->breadcrumbs->add(new Breadcrumb('../', 'Languages'));

        $id = (int) $this->input->get->id;

        if($id > 0) {
            // edit existing record
            $result = $this->db->query("SELECT * FROM {$this->table_name} WHERE id=$id");
            list($id, $language, $code, $home, $default, $sync) = $result->fetch_array();
            $home = $this->_getPath($home); 
            $this->setFuel('processHeadline', "Edit language");
            
        } else {
            // add new record
            $id = 0;
            $language = '';
            $code = '';
            $home = '';
            $default = 0;
            $sync = 0; 
            $this->setFuel('processHeadline', "Add language");
        }

        $form = $this->modules->get("InputfieldForm");
        $form->method = 'post';
        $form->action = '../save/';

        $field = $this->modules->get("InputfieldHidden");
        $field->name = 'id';
        $field->value = $id;
        $form->add($field);

        $field = $this->modules->get("InputfieldText");
        $field->label = 'Language';
        $field->description = 'Name of the language (e.g. \'English\').';
        $field->name = 'language';
        $field->value = $language;
        $form->add($field);

        $field = $this->modules->get("InputfieldText");
        $field->label = 'Code';
        $field->description = 'Language’s ISO or LCID code (e.g. \'en\' or \'en-au\').';
        $field->name = 'code';
        $field->value = $code;
        $form->add($field);
        
        $field = $this->modules->get("InputfieldCheckbox");
        $field->label = 'Default';
        $field->description = 'Is this language to be used by default?';
        $field->name = 'default';
        $field->value = 1;
        if($default) {
         $field->attr('checked', 'checked');
         $field->attr('disabled', 'disabled'); 
        } else {
         $field->attr('onClick', "if($('#Inputfield_default').prop('checked')) { $('#Inputfield_sync').prop('checked', false).prop('disabled', true); } else {  $('#Inputfield_sync').prop('disabled', false); }");
        }
        $form->add($field);     
        
        /*
         * Sync functionality is to be added in a later release
         *
        $field = $this->modules->get("InputfieldCheckbox");
        $field->label = 'Sync';
        $field->description = 'Sync future changes of this language’s page tree with default one automatically? Will maybe change with next version.';
        $field->name = 'sync';
        $field->value = 1;
        if($sync) {
         $field->attr('checked', 'checked');
        }
        if($default) {
         $field->attr('disabled', 'disabled'); 
        }
        $form->add($field);             
         *
         */

        // offer option to clone another language’s page structure on creation of a new language
        if($id == 0) {
            $field = $this->modules->get("InputfieldSelect");
            $field->label = 'Populate new language';
            $field->description = 'Clone another language’s page structure?';
            $field->name = 'clone';
            $field->addOption(0, '---');
            $sql = "SELECT `id`, `language`, `default` FROM {$this->table_name} ORDER BY `language`";
            $result = $this->db->query($sql);
            while($l = $result->fetch_assoc()) {
                $field->addOption($l['id'], $l['language']. ($l['default']?" (default)":""));
            }
            $form->add($field);
        }
        
        // now add a script that makes it automatically populate the redirect_to field
        // with the URL of the selected page.

        $field = $this->modules->get("InputfieldButton");
        $field->type = 'submit';
        if($id > 0 ) {
            $field->value = 'Update language';
        } else {
            $field->value = 'Add new language';
        }

        $form->add($field);

        return $form->render();
    }   


    /**
     * Save language - Called when the URL is ./save/
     *
     */
    public function ___executeSave() {

        $id = (int) $this->input->post->id;
        $language = $this->input->post->language;
        $code = $this->input->post->code;
        $default = ($this->input->post->default)?$this->input->post->default:0;
        $sync = ($this->input->post->sync)?$this->input->post->sync:0;
        $clone = $this->input->post->clone;

        if ($language == '' || $code == '') {
            $this->error("No language created, please check your values.");
            $this->session->redirect("../"); // back to list
        }

        $this->_saveLanguage($language, $code, $default, $sync, $id, $clone);

        $this->message("Saved language named \"$language\" with code \"$code\".");
        $this->session->redirect(".."); // back to list

    }


    /**
     * Delete language - Called when the URL is ./save/
     *
     */
    public function ___executeDelete() {

        $count = 0;
        
        if(!is_array($this->input->post->delete) || empty($this->input->post->delete)) {
            $this->message("Nothing to delete");
            $this->session->redirect("../"); // back to list
        }
        
        
        $form = $this->modules->get("InputfieldForm"); 
        $form->attr('action', '../delete/'); 
        $form->attr('method', 'post'); 
        
        $field = $this->modules->get("InputfieldHidden");
        $field->attr("name", "confirm");
        $field->attr("value", 1);
        $form->add($field);

        foreach($this->input->post->delete as $id=>$delmode) {
            $sql = "SELECT `language`, `home` FROM {$this->table_name} WHERE `id` = {$id}";
            $result = $this->db->query($sql);
            if($row_lang = $result->fetch_assoc()) {
                if($delmode == 1) {
                    $id = (int) $id;
                    $field = $this->modules->get("InputfieldSelect"); 
                    $field_name = "delete[{$id}]";
                    $field->label = $row_lang['language']; 
                    $field->attr("id+name", $field_name); 
                    $field->addOption(0, "Deal with language’s pages...");
                    $field->addOption("delete", "- delete language’s page tree");
                    $field->addOption("unlock", "- just keep and unlock language’s page tree");
                    
                    $form->add($field);
                    $count++;                   
                } else {
                    if($this->_deleteLanguage($id, $delmode)) {
                      $count++;
                    }
                }
            }
        }
        $form->description = "Delete {$count} language(s).";
        if($this->input->post->confirm) {
            $this->message("Deleted " . $count . " language(s).");
            $this->session->redirect("../"); // back to list            
        } else {
            $button = $this->modules->get("InputfieldButton");
            $button->type = 'submit';
            $button->id = 'confirm_delete';
            $button->value = 'Confirm';
            $form->add($button);
            return $form->render();
        }
    }


    /**
     * Adds the language tab to the page edit form
     * 
     */     
    private function _extendForm($form, $page, $currLang=null) {
        // if language’s homepage, lock template field
        if($page->parents->path == "/") {
            $settings = $form->children('id=template');
            current(current($settings))->attr('disabled', 'disabled');
        }
        // first we have to find and remove the delete link, temporarly
        $delete = $form->children('id=ProcessPageEditDelete')->first();
        $form->remove($delete);
        // after that we have to find and remove the view link, also temporarily
        if($view = $form->children('id=ProcessPageEditView')->first()) {
            $form->remove($view);
        }

        // create the tab
        $tab = new InputfieldWrapper();
        $tab->attr('id', 'tabLanguages'); 
        $tab->attr('title', 'Languages');

        // get associated language versions of current page
        $sql = "
        SELECT `language`, `page`, `group` FROM {$this->table_name}_pagemap
        WHERE `group` = (
            SELECT `group` FROM {$this->table_name}_pagemap 
            WHERE `language` = {$currLang}
            AND `page` = {$page->id}
            )
        ";
        $res_pmap = $this->db->query($sql);

        // set group = 0 by default
        $group = 0;
        
        // store all associated pages
        $pagemap = array();
        while($map = $res_pmap->fetch_assoc()) {
            $pagemap[$map['language']] = $map['page'];
            if($group == 0) {
               $group = $map['group'];
            }
        }

        // now list our languages
        $sql = "SELECT `id`, `language`, `code` FROM {$this->table_name} ORDER BY `language`";
        $res_lang = $this->db->query($sql);
        $translation = array();
        $cntLang = 0;
        while($l = $res_lang->fetch_assoc()) {
            if($l['id'] !== $currLang) {
                $cntLang++;           
                // for each language not the current, create a language field
                $field = $this->modules->get("InputfieldMarkup"); 
                $field->label = $l['language'];
                $field->attr('id', 'language-'.$l['language']);

                if(array_key_exists($l['id'], $pagemap)) {
                    // if there is another language version associated with this page, do:
                    $trans_page = $this->pages->get($pagemap[$l['id']]);
                    $field->value = "\n<ul class='PageArray'>";
                    $field->value .= "\n\t<li><a href='../edit/?id={$trans_page->id}'>{$trans_page->title}</a></li>";
                    $field->value .= "\n</ul>";
                  
                } else {
                    // if not, do:
                    // get parent of current page
                    $parent = $page->parent();
                    // check if there is a translation of parent
                    $sql = "
                      SELECT `id`, `page`, `group` FROM {$this->table_name}_pagemap 
                      WHERE `language` = '{$l['id']}'
                      AND `group` = (
                          SELECT `group` FROM {$this->table_name}_pagemap
                          WHERE `page` = {$parent->id}
                          )
                    ";
                    $res_parent = $this->db->query($sql);
                    if($res_parent->num_rows) {
                      
                        // get translated parent’s data
                        $row_parent = $res_parent->fetch_assoc();
                        $translation[$l['id']] = $this->modules->get("InputfieldSelect"); 
                        $field_id = "CreateTranslation_".$l['id'];
                        $translation[$l['id']]->attr("id+name", $field_id); 
                        $translation[$l['id']]->addOption(0, "Create a translation...");
                        $translation[$l['id']]->addOption("clone", "- by cloning current page");
                        $translation[$l['id']]->addOption("new", "- by creating an new empty page");
                        //$translation[$l['id']]->attr("onChange", "document.getElementById('ProcessPageEdit').submit()");
                        
                        $field->value = $translation[$l['id']]->render();
                        
                        // since this isn't technically part of the page's fields, we have to
                        // handle any input submitting to the field if we want it.
                        if($this->input->post->$field_id !== null) {
                            // how to create the translation page
                            $translation[$l['id']]->processInput($this->input->post); 
                            $value = $translation[$l['id']]->attr('value');
                            // parent’s translation
                            $trans_parent = $this->pages->get($row_parent['page']);
                            if($trans_parent->id) {
                                if($value == "clone") {
                                     if($trans_page = $this->_copyPage($page, $trans_parent, $l['id'], false)) {
                                         // has anything to happen here?
                                     }
                                } elseif($value == "new") {
                                     $trans_page = new Page($page->template);
                                     $trans_page->parent = $trans_parent;
                                     $trans_page->title = $this->translation_prefix.$page->title;
                                     $trans_page->save();
                                     $this->_mapPage($trans_page, $group, $l['id'], true);
                                }
                            }
                        } else {
                            // retrieve the value from the session var.
                            $field->attr('value', $this->session->fieldExample2);
                        }
                        
                    } else {
                        // parent wasn’t found
                        $field->value = "There is no translation of the current page’s parent."; 
                    }
                }
                
                // add the field to the tab
                $tab->add($field);
            }
        }
        if($cntLang == 0) {
              $field = $this->modules->get("InputfieldMarkup"); 
              $field->label = "Start translating now!";
              $field->value = "<a href=\"../../setup/languages/\">Manage Languages</a>";
              $tab->add($field);
        }


        // add the tab to the form
        $form->add($tab);

        // now add that delete and view link back to the form at the end  
        $form->add($delete);
        if($view) {
            $form->add($view);
        }
    }   
    
    
/******
 ***** hooking methods
 ***** 
 *****/

    /**
     * If page has just been created, it’s marked as new for being checked after $page->save
     * triggered before $page->save
     */ 
    public function markNewPage($event) { 
        $page = $event->arguments[0]; 
        if(!$page->id) { 
            // set your own var name to check after page saved
            $page->this_is_new = true; 
            // page is new
            return true;
        } else {
            // page is an existing page
            $this->_validateChanges($page);
            return false;
        }      
    }
    
    
    /**
     * Creates pagemap entry for new page or after being moved
     * triggered after $page->save
     */     
    public function pagemapSavedPage($event) {
        $page = $event->arguments[0];
        if($page->this_is_new) {
            // create new pagemap entry
            unset($page->this_is_new);
            if($language = $this->hasLanguage($page->parent)) {
                return $this->_mapPage($page, $this->_getNewGroup(), $language);
            } else {
                return true;
            }
        } else {
            if((is_object($page->parentPrevious)) && ($page->parentPrevious->id != null)) {
               if($this->hasLanguage($page->parentPrevious)) {
                   if($language = $this->hasLanguage($page->parent)) {
                       return $this->_mapPage($page, 0, $language, true, true);
                   } else {
                       return $this->_unmapPage($page, true); 
                   }
               } else {
                   if($language = $this->hasLanguage($page->parent)) {
                       return $this->_mapPage($page, $this->_getNewGroup(), $language, true, true);
                   } else {
                       return true;
                   }
               }                
            }
            /*
            * Sync code would be added here.
            * After restructuring the if conditions.
            */            
        }
    }
    
    /**
     * Delets a pagemap entry on deletion of a page of a language page tree
     * and its translations
     * triggered after $page->delete
     *  
     * Found to be unnecessary. For now. :)
    public function unpagemapPage($event) {
        $page = $event->argument[0];
        if($page->process != "ProcessPageTrash") {
            if($translations = $this->getTranslations($page->id)) {
                foreach($translations as $translation) {
                    $trans_page = $this->pages->get($translation);
                    if($this->pages->delete($trans_page)) {
                        $this->_upmapPage($trans_page);
                    }
                }
            }
        }
        return $this->_unmapPage($page);
    }
      *
      */
    
    /**
     * Adds the language management form tab to the page edit form
     * triggered before ProcessPageEdit::afterForm
     */ 
    public function addLanguageManagement($event) {
        $page = $this->pages->get($this->input->get->id);
        if(($page->id) && ($currLang = $this->hasLanguage($page))) {
            $form = $event->return; 
            $this->_extendForm($form, $page, $currLang); 
            $event->return = $form; 
        }
        $this->removeHook($this->hooks['addLanguageManagement']);
    }
    

    
/******
 ***** language setting, checking
 ***** 
 *****/
 
    /**
     * Set current language
     *
     */
    public function setLanguage($event) {
        // get event’s page
        $page = $event->data['object'];
        // check if it is the root page
        if($page->path == '/') {
            // yes: so redirect to the default language
            // now get default languages path and code from DB
            $sql = "SELECT home, code FROM `{$this->table_name}` WHERE `default` = '1'";
            $result = $this->db->query($sql);
            $result_arr = $result->fetch_array();
            // set language 
            $this->session->set("language", $result_arr['code']);
            // convert page’s id to httpURL
            $redirect_to = $this->_getPath($result_arr['home'], true);
            // redirect
            $this->session->redirect($redirect_to);
        } else {
            // no:
            // set up template var, if page is in language tree
            if($tmpl_var = $this->_setTemplateVar($page)) {
                 Wire::setFuel('languages', $tmpl_var); 
                 return true;
            } else {
                 return false;
            }
            // if not, detect current language...
            if($code = $this->hasLanguage($page, true)) {
              // ...and set new language
              $this->session->set("language", $code);
            } else {
              // has no language
              return false; 
            }
        }
        $this->removeHook($this->hooks['defaultLanguage']);
    }
 
 
    /**
     * Check, if passed $page is a subpage of a language’s page structure
     *
     */ 
    public function hasLanguage($page, $return_code=false) {
       if(is_numeric($page)) {
           $this->pages->get($page);
       }
       if($page->id !== null) {
           // get all home page ids and related language codes
           $sql = "SELECT `id`, `home`, `code` FROM {$this->table_name}";
           $result = $this->db->query($sql);
           // get the upper hierarchy of page
           $parent = $page->rootParent;
           // check if first level parent or page itself is a language’s home page
           while($l = $result->fetch_assoc()) {
               if(
                   ($page->id == $l['home']) // check, if page is a language’s home page or ...
                   ||
                   ($parent->id == $l['home']) // a parent is a language’s home page
                 ) {
                   if($return_code) {
                       return $l['code'];
                   } else {
                       return $l['id'];
                   }
                 }
           }
           return false;
       } else {
           return false;
       }
    }
    
    /**
     * get language by id
     */ 
    public function getLanguage($language) {
       if(is_numeric($language)) {
           $sql = "
               SELECT
               *
               FROM
               {$this->table_name}
               WHERE
               `id` = '{$language}'
               ";    
       } elseif(is_scalar($language)) {
           $sql = "
               SELECT
               *
               FROM    
               {$this->table_name}
               WHERE
               `code` = '{$language}'
               ";              
       } else {
           return false;
       }
       $result = $this->db->query($sql);
       if($row = $result->fetch_assoc()) {
           return $row;
       } else {
           return false;
       }
    }

    /**
     * get list of languages
     * all or just synced ones
     */ 
    public function getLanguages($synced=null) {    
        $languages = array();
        if($synced === null) {
            $sql = "
               SELECT
               *
               FROM
               {$this->table_name}
               ORDER BY `language`
               ";
       } elseif(is_numeric($synced) || is_boolean($synced)) {
           $sql = "
               SELECT
               *
               FROM
               {$this->table_name}
               WHERE `sync` = ".($synced?1:0)."
               ORDER BY `language`";
       } else {
            return false;
       }
       $result = $this->db->query($sql);
       while($row = $result->fetch_assoc()) {
           $languages[$row['id']] = $row;
       }
       return $languages;
    }
    
    /**
     * get passed page’s translations
     *
     */ 
    public function getTranslations($page, $return_obj=false, $include_current=false) {
       if(is_object($page)) {
           $page_id = $page->id;
       } elseif(is_numeric($page)) {
           $page_id = $page;
       } else {
           return false;
       }
       $translations = array();
       $sql = "
           SELECT
           t1.`code`,
           t2.`page`
           FROM
           {$this->table_name} t1
           LEFT JOIN
           {$this->table_name}_pagemap t2
           ON
           t1.`id`=t2.`language`
           AND
           t2.`group` = (
               SELECT
               t3.`group`
               FROM
               {$this->table_name}_pagemap t3
               WHERE
               t3.`page` = '{$page_id}'
               )
           ";
       $result = $this->db->query($sql);
       while($translation = $result->fetch_assoc()) {
           if($translation['page'] != $page_id || $include_current) {
               if($return_obj) {
                   $translations[$translation['code']] = $translation['page']?$this->pages->get($translation['page']):null;   
               } else {
                   $translations[$translation['code']] = $translation['page']?$translation['page']:null;   
               }
           }
       }
       return count($translations)?$translations:false; 
    }
    
    /**
     * check, if page has any translations
     *
     */ 
    public function hasTranslations($page) {
       if(is_object($page) && $page->id != null) {
           $page_id = $page->id;
       } elseif(is_numeric($page)) {
           $page_id = $page;
       } else {
           return false;
       }
       $translations = array();
       $sql = "
           SELECT
           count(t1.page) AS translations
           FROM
           {$this->table_name}_pagemap t1
           WHERE
           t1.`group` = (
               SELECT
               t2.`group`
               FROM
               {$this->table_name}_pagemap t2
               WHERE
               t2.`page` = '{$page_id}'
               )
            AND t1.page != '{$page_id}'
           ";
       $result = $this->db->query($sql);
       if($row = $result->fetch_assoc()) {
           return $row['translations'];
       }
       return false;    
    }

    
    /**
     * translate page to a specific language or to all synced ones
     * $to_language = null for creating translations in all other languages
     * $to_language = "synced" for creating translations in all synced languages
     * optional recursive
     */ 
    public function translatePageTo($page, $to_language=null, $recursive = true) {
       if(is_object($page) && $page->id != null) {
            if($curr_lang = $this->hasLanguage($page)) {
                if($to_languages != null) {
                    if($to_languages == "synced") {
                        $languages = $this->getLanguages(true);
                    } elseif(is_numeric($to_language)) {
                        if($language = $this->getLanguage($to_language)) {
                            $languages = array($to_language=>$language);
                        } else {
                            return false;
                        }
                    } else {
                        return false;   
                    }
                } else {
                    $languages = $this->getLanguages();
                }
                if(count($languages)) {
                    $translated = 0;
                    // get translations of parent
                    if($parents = $this->getTranslations($page->parent)) {
                      foreach($languages as $language) {
                          if($language['id'] != $curr_lang) {
                              if($this->_copyPage($page, $parents[$language['id']], $language['id'], $recursive)) {
                                $translated++;
                              }
                          }   
                      }
                      return !(count($languages) - $translated);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
       } else {
           return false;
       }
    }
    
    /**
     * check if is default
     *
     */
    public function isDefault($language) {
        if(is_scalar($language)) {
            if(is_numeric($language)) {
                $sql = "SELECT `id` AS `language` FROM {$this->table_name} WHERE `default` = 1";
            } else {
                $sql = "SELECT `code` AS `language` FROM {$this->table_name} WHERE `default` = 1";   
            }
            $result = $this->db->query($sql);
            if($row = $result->fetch_assoc()) {
                if($row['language'] == $language) {
                    return true;   
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }      
    }
 

    /**
     * Save language - actually saves the language to the db ;)
     *
     */
    private function _saveLanguage($language, $code, $default, $sync, $id = 0, $clone=0) {
        $language = $this->db->escape_string($language);
        $code = $this->db->escape_string($code);

        if ($id == 0) {
            // Set home page of language
            $home = $this->pages->get("/".$code."/");
            if(!$home->id) {
                $home = new Page($this->templates->get($this->template_home));
                $home->parent = $this->pages->get("/");
                $home->title = 'Home '.$language;
                $home->name = $code;
                $home->parent = $this->pages->get("/");
                $home->save();   
            }
            $sql = "INSERT INTO `{$this->table_name}` SET `language` = '$language', `code` = '$code', `home` = '".$home->id."', `default` = '$default', `sync` = '$sync'  ON DUPLICATE KEY UPDATE id = id;";
            $result = $this->db->query($sql);
            $id = $this->db->insert_id;
            $this->_mapPage($home->id, 1, $id, true);
            if($clone) {
                $this->clonePageTree($clone, $id);
            }
        } else {
            $sql = "UPDATE `{$this->table_name}` SET `language` = '$language', `code` = '$code', `default` = '$default', `sync` = '$sync' WHERE id = $id";
            $result = $this->db->query("
             SELECT `home`, `language`, `code`
             FROM `{$this->table_name}`
             WHERE
             `id` = '$id'
             ");
            $result_arr = $result->fetch_array();
             
            if($home = $this->pages->get($result_arr['home'])) {
                 $home->name = $code;
                 $home->title = str_replace($result_arr['language'], $language, $home->title);
                 $home->save();
            }
            $result = $this->db->query($sql);
            
        }
        // If marked as default language, unmark current default language
        if($default) {
            $this->db->query("
                UPDATE `{$this->table_name}`
                SET `default` = '0',
                `sync` = '1'
                WHERE `id` <> '$id'
                AND `default` = '1'
                ");
        }


        return $result;
    }


    /**
     * Delete language - Actually deletes language
     *
     */ 
    private function _deleteLanguage($language, $mode="delete") {
        $sql = "SELECT `home`, `default` FROM {$this->table_name} WHERE `id` = {$language}";
        $result = $this->db->query($sql);
        
        if($row = $result->fetch_assoc()) {
            $default = $row['default'];
            if($default) throw new WireException("Default language can’t be deleted."); 
            $home = $row['home'];
            // delete language entry
            $sql = "DELETE FROM {$this->table_name} WHERE `id` = {$language} LIMIT 1";
            $result = $this->db->query($sql);
            if($this->db->affected_rows) {
                if($mode == "delete") {
                    if($this->_deletePageTree($home)) {
                        return true;
                    } else { die("Deletion of Page Tree didn’t work out");
                        return false;
                    }                
                } elseif($mode == "unlock") {
                    if($this->_unmapPageTree($language)) {
                        $home = $this->pages->get($row['home']);
                        if($home->id) {
                            $change_template_to = $home->children->first()->template;
                            $home->template = $change_template_to;
                            $home->save();
                        } else {
                            return false;
                        }
                        return true; 
                    } else {
                        return false;
                    }                   
                }
                return true;
            } else {
                return false;
            }
            return true;
        } else {
            return false;
        }        
    }
  
 
/******
 ***** language page tree manipulation
 ***** 
 *****/
 
    /**
     * Clone language’s page structure
     *
     */
    public function clonePageTree($from_language_id, $to_language_id) {
        $sql = "SELECT `home`, `id` FROM {$this->table_name} WHERE `id` IN (".$from_language_id.", ".$to_language_id.")";
        $result = $this->db->query($sql);
        $from_page = null;
        $to_page = null;
        while($l = $result->fetch_assoc()) {
            if($l['id'] == $from_language_id) {
                $from_page = $this->pages->get($l['home']);
            } elseif($l['id'] == $to_language_id) {
                $to_page = $this->pages->get($l['home']);                
            }
        }
        if($from_page->id !== null && $to_page->id !== null) {
            // now page tree roots are set
            // first level of selecting pages for cloning will be done here
            $children = $from_page->children("include=all");
            foreach($children as $i=>$child) {
                if(!$this->_copyPage($child, $to_page, $to_language_id, true)) {
                    return false;
                }
            }
            return true;
        } else {
            // something went wrong here
            return false;
        }
    }
    
    
    /**
     * Delete language’s page structur
     *
     */ 
    private function _deletePageTree($home) {
        if(is_numeric($home)) {
            $home = $this->pages->get($home);
            if($home->id) {
                // unmap it
                if(!$this->_unmapPage($home, true)) {
                    die("unmap of page tree didn’t work out");
                    return false;
                }
                // delete it
                if(!$this->pages->delete($home, true)) {
                    die("\$pages->delete returned false");
                    return false;   
                }
                return true;
            } else {
                die("SQL didn’t get the language’s homepage: {$sql}.");
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Clone a page as child of a new parent
     * optionally mapped to language and/or recursive
     */ 
    private function _copyPage($page, $to_parent, $map_to_language=false, $recursive=false) { 
        if(
            (is_object($page) && ($page->id))
            &&
            (is_object($to_parent) && ($to_parent->id))
        ) {

            $clone = $this->pages->clone($page, $to_parent, false);
            // re-init $clone to get rid of the parentPrevious keeping me from saving the new set title
            // is there another solution for this?
            // $clone->set("parentPrevious", null); doesn’t work
            // $clone->parentPrevious = null; doesn’t work
            $clone = $this->pages->get($clone->id);
            $clone->setFieldValue("title", $this->translation_prefix.$page->title);            
            $this->pages->save($clone);
            if($clone->id) { 
                if(is_numeric($map_to_language) && $map_to_language > 0) {
                    if($group = $this->_getGroup($page)) {
                        if(!$this->_mapPage($clone, $group, $map_to_language, true)) {
                            // something went wrong here
                            die("Mapping didn’t work out");
                            return false;
                        }
                    }
                }
                if($recursive) {
                    $children = $page->children("include=all");
                    foreach($children as $i=>$child) {
                        if($child->parent->id == $page->id) {
                            if(!$this->_copyPage($child, $clone, $map_to_language, $recursive)) {
                                throw new WireException("Cloning didn’t work out.");
                                return false;
                            }
                        }
                    }
                } 
                return $clone->id;
            } else {
                throw new WireException("Clone got no id");
                return false;
            }            
        } else {
            throw new WireException("Params \$page or \$to_parent aren’t objects or have no ids.");
            return false;
        }
    }


    /**
     * Applies the changes in $page’s language page tree to the mapped translations in the other language’s page trees
     * 
     */
    private function _syncPage(&$page) {
        if(is_object($page) && $page > 0) {
            // work around because I can't get the id
            $page->id = (int) "".$page."";
            // array to store languages
            $languages = array();
            // syncs to be done stored here
            $syncs = array();
            // current page’s group
            $group = $this->_getGroup($page);
            // get languages to be synced
            $sql_lang = "SELECT `id` FROM {$this->table_name} WHERE `sync` = 1";
            $result_lang = $this->db->query($sql_lang);
            if($row_lang = $result_lang->fetch_assoc()) {
                $languages[$row_lang['id']] = array("page" => 0, "parent" => 0);
            } else {
                return false;
            }
            // get all translations of page
            $sql_pages = "SELECT `language`, `page` FROM {$this->table_name}_pagemap WHERE `group` = {$group}";        
            $result_pages = $this->db->query($sql_pages);
            while($row_pages = $result_pages->fetch_assoc()) {
                if(array_key_exists($row_pages['language'], $languages)) {
                    if($row_pages['page'] == $page->id) {
                       unset($languages[$row_pages['language']]);
                    } else {
                       $languages[$row_pages['language']]['page'] = $this->pages->get($row_pages['page']);
                    }
                }
                // store current page’s language
                if($row_pages['page'] == $page->id) {
                    $current_language = $row_pages['language'];
                }
            }
            
            // sync templates?
            if($page->templatePrevious) {
                $syncs['template'] = true;
            }                        
            
            // sync parent?
            if($page->parentPrevious) {
                $syncs['parent'] = true;
                // check, if new parent is part of a language tree
                if($lang = $this->hasLanguage($page->parent)) {
                    if($lang == $current_language) {
                        $syncs['parent_haslanguage'] = true;
                    } else {
                        // if page is moved to another language’s page tree, throw exception
                        throw new WireException("Page can’t be moved from one language’s page tree to another.");   
                    }
                } else {
                    $syncs['parent_haslanguage'] = false;
                }
            }
            // get parents
            $parent = $page->parent;
            $sql_parent = "SELECT `language`, `page` FROM {$this->table_name}_pagemap WHERE `group` = (SELECT `group` FROM {$this->table_name}_pagemap WHERE `page` = {$parent->id})";        
            $result_parent = $this->db->query($sql_parent);
            while($row_parent = $result_parent->fetch_assoc()) {
                if($row_parent['page'] != $parent->id) {
                    $languages[$row_parent['language']]['parent'] = $row_parent['page'];
                }

            }                
        
            foreach($languages as $language => &$translation) { 
                // if translation doesn’t exist, yet, create it
                if($translation['page'] == 0 && $translation['parent'] > 0) {
                    $translation['page'] = $this->pages->get($this->_copyPage($page, $this->pages->get($translation['parent']), $language));
                }
                // sync templates
                if($syncs['template']) {
                    $translation['page']->template = $page->template;
                }
                // sync parents
                if($syncs['parent'] && $translation['parent'] > 0) {
                    if($syncs['parent_haslanguage']) {
                        // if $page is moved inside the language’s page tree, translations are moved to the new parent’s translation
                        $translation['page']->parent = $this->pages->get($translation['parent']);
                    } else {
                        // if $page is moved outside the language’s page tree, translations are moved to the same parent
                        $translation['page']->parent = $this->pages->get($page->parent);
                        // and are unmapped
                        $this->_unmapPage($translation['page'], true);
                    }
                } else {
                    // here has to be an error handling implemented to deal with missing parents   
                }
                
                // sync status
                $translation['page']->set("status", $page->status);
                
                // sync sort
                $translation['page']->sort = $page->sort;
                
                // save translation
                $translation['page']->save();

            }
            return true;
        }
    }    
    
    /**
     * Validate changes before saving.
     * 
     */
    private function _validateChanges($page) {
        // Will page be moved?
        if((is_object($page->parentPrevious)) && ($page->parentPrevious->id != null)) { 
            if($this->hasTranslations($page) > 0) { 
                throw new WireException("Can’t move page mapped to translations in other language trees.");
                return false;
            }
            if(($language = $this->hasLanguage($page)) && ($language_parent = $this->hasLanguage($page->parent))) {
                if($language !== $language_parent) throw new WireException("Page can’t be moved from one language page tree to another."); 
                return false;
            }
        }
        return true;
    }    
    


/******
 ***** pagemapping
 ***** 
 *****/

    /**
     * maps a page to $to_group as translation in $to_language
     * map entry can be $remap’ed optionally, as well as children pages can be mapped recursively (not very logical, yet)
     */
     private function _mapPage($page, $to_group=0, $to_language, $remap=false, $recursive=false) {
        if(is_numeric($page)) {
            $page = $this->pages->get($page);   
        }    
        if(
            (is_object($page) && $page->id)
            &&
            is_numeric($to_group)
            &&
            is_numeric($to_language)
        ) {
            // check, if page is mapped, yet
            $sql = "SELECT `id` FROM {$this->table_name}_pagemap WHERE `page` = {$page->id} AND `language` = {$to_language}";
            $result = $this->db->query($sql);

            if($result->num_rows == 0) {
                if($to_group == 0) {
                    $to_group = $this->_getNewGroup();   
                }
                $sql = "
                    INSERT INTO {$this->table_name}_pagemap
                    (`group`, `language`, `page`)
                    VALUES
                    ({$to_group}, {$to_language}, {$page->id})
                    ";
                $result = $this->db->query($sql);
                if($id = $this->db->insert_id) {
                    // if recursive, go deeper
                    if($recursive) {
                        $children = $page->children("include=all");
                        foreach($children as $i=>$child) {
                            if(!$this->_mapPage($child, 0, $to_language, $remap, $recursive)) {
                                return false;
                            }
                        }
                    }
                    
                    // return map entry id                    
                    return $id;
                } else {
                    // something went wrong here
                    die("no id");
                    return false;
                }
            } else {
                // get id
                $row = $result->fetch_assoc();
                if($remap && $to_group > 0) { 
                    $sql = "UPDATE {$this->table_name}_pagemap SET `group` = {$to_group} WHERE `page` = {$page->id}";
                    $result = $this->db->query($sql);
                    if($this->db->affected_rows) {
                        return $row['id'];
                    } else {
                        die("No affected rows");
                        return false;
                    }
                } else {
                    // return existing map entry id
                    return $row['id'];
                }
            }
        } else {
            die("no objects");
            return false;
        }
    }
    
         


    /**
     * Unmaps a page, or recursively a part of the page tree
     * 
     */
     private function _unmapPage($page, $recursive=false) {
        if(is_numeric($page)) {
            $page_id = $page;
            $page = $this->pages->get($page_id);
        } elseif(is_object($page) && $page->id) {
            $page_id = $page->id;  
        } else {
            return false;
        }
        $sql = "DELETE FROM {$this->table_name}_pagemap WHERE `page` = {$page_id}";
        $result = $this->db->query($sql);
        if($this->db->affected_rows) {
            if($recursive) {
                $children = $page->children("include=all");
                foreach($children as $i=>$child) {
                    if(!$this->_unmapPage($child, $recursive)) {
                        die("unmap recursion didn’t work out");
                        return false;
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }
     
     
    /**
     * Kicks out all pagemap entries of $language
     * 
     */    
    private function _unmapPageTree($language) {
         if(is_numeric($language)) {
            $sql = "DELETE FROM {$this->table_name} WHERE `language` = {$language}";
            $result = $this->db->query($sql);
            return $this->db->affected_rows;
         } else {
            return false;
         }
    }
    
    
    /**
     * Gets new pagemap group id
     *
     */ 
    private function _getNewGroup() {
        $sql = "SELECT (1+MAX(`group`)) AS `next_group` FROM {$this->table_name}_pagemap";
        $result = $this->db->query($sql);
        if($row = $result->fetch_assoc()) {
            return ($row['next_group'] == 0)?1:$row['next_group'];
        }
    }   
    

    /**
     * Gets current group of $page
     *
     */ 
    private function _getGroup($page) {
       if(is_numeric($page)) {
           $page_id = $page;
       } elseif(is_object($page) && $page->id) {
           $page_id = $page->id;   
       } else {
           return false;
       }
       $sql = "SELECT `group` FROM {$this->table_name}_pagemap WHERE `page` = '{$page_id}'";
       $result = $this->db->query($sql);
       if($row = $result->fetch_assoc()) {
           return $row['group'];
       } else {
           return false;
       }
    }   
    
    
/******
 ***** misc little helpers
 ***** 
 *****/ 

    /**
     * returns the path or httpUrl associated with a passed page_id
     *
     */
    private function _getPath($page_id, $http=false) {
        // if page_id is a valid page ID, convert it to be the URL to the page
        if(!is_numeric($page_id)) return $page_id; 
        $page = $this->pages->get((int) $page_id); 
        // we get httpUrl during actual redirects so that it will redirect to http/https depending on template setting.
        // this prevents ProcessWire from doing an extra redirect to the https version if the template calls for it. 
        if($page->id) $path = $page->get($http ? 'httpUrl' : 'path'); ; 
        return $path; 
    }


/******
 ***** Install/Uninstall
 ***** 
 *****/ 

    /**
     * Installation
     *
     */
    public function ___install() {
        parent::___install();

        // create admin page
        $p = new Page();
        $p->template = $this->templates->get("admin");
        $p->parent = $this->pages->get("template=admin, name=setup");
        $p->title = 'Languages';
        $p->name = 'languages';
        $p->process = $this;
        $p->save();
        
        // create home_language template
        $root = $this->pages->get("/");
        $template = clone $root->template;
        $template->id = 0;
        $template->fieldgroup->id = 0;
        $template->fieldgroup->name = $this->template_home;
        $template->fieldgroup->save();
        $template->name = $this->template_home; 
        if((strlen($root->template->filename)) && (file_exists($root->template->filename))) {
          $filename_new =  $this->config->paths->templates.$template->name.".php";
          if(!file_exists($filename_new)) {
              copy($root->template->filename, $filename_new);
              $template->set('filename', $template->name.".php");
          }
        }
        $template->set('parentTemplates', $root->template->id);
        $template->set('noMove', true);     
        $template->save();
        
        // create first language home page
        $home = $this->pages->get("/en/");
        if(!$home->id) {
            $home = new Page($template);
            $home->parent = $this->pages->get("/");
            $home->title = 'Home English';
            $home->name = 'en';
            $home->save();
        }

        $sql_languages = "

        CREATE TABLE `{$this->table_name}` (
        `id` TINYINT NOT NULL AUTO_INCREMENT ,
        `language` VARCHAR( 30 ) NOT NULL ,
        `code` VARCHAR( 10 ) NOT NULL ,
        `home` INT NOT NULL ,
        `default` TINYINT NOT NULL,        
        `sync` TINYINT NOT NULL DEFAULT '0',
        PRIMARY KEY ( `id` ) ,
        INDEX ( `id` ),
        UNIQUE KEY(`code`),
        UNIQUE KEY(`home`)
        ) ENGINE = MYISAM;
        ";
        $this->db->query($sql_languages);
        
        $sql_firstlang = "

        INSERT INTO `{$this->table_name}`
        ( `language`, `code`, `home`, `default` )
        VALUES
        ( 'English', 'en', {$home->id}, 1 );
        ";
        $this->db->query($sql_firstlang);
        $language = $this->db->insert_id;


        $sql_pagemap = "
    
        CREATE TABLE {$this->table_name}_pagemap (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `group` INT NOT NULL DEFAULT '0',
        `language` TINYINT NOT NULL DEFAULT '0',
        `page` INT NOT NULL DEFAULT '0',
        INDEX ( `group` ) ,
        UNIQUE (
        `page`
        )
        ) ENGINE = MYISAM; 
        ";
        
        $this->db->query($sql_pagemap);
        $group = $this->_getNewGroup();
        
        
        $sql_pagemap_entry = "
        INSERT INTO {$this->table_name}_pagemap
        (`group`, `language`, `page`) VALUES ('{$group}', '{$language}', '{$home->id}')
        ";
        
        $this->db->query($sql_pagemap_entry);       
        

    }


    /**
     * Uninstallation
     *
     */
    public function ___uninstall() {
        $p = $this->pages->get('template=admin, name=languages');
        $p->delete();
        // Here the language home template has to be replaced or at least unlocked.

        $this->db->query("DROP TABLE {$this->table_name}");
        $this->db->query("DROP TABLE {$this->table_name}_pagemap");     
        parent::___uninstall();
    }
}