<?php
/**
 * The control file of product module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2010 QingDao Nature Easy Soft Network Technology Co,LTD (www.cnezsoft.com)
 * @license     LGPL (http://www.gnu.org/licenses/lgpl.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     product
 * @version     $Id$
 * @link        http://www.zentao.net
 */
class product extends control
{
    private $products = array();

    /**
     * Construct function.
     * 
     * @access public
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        /* Load need modules. */
        $this->loadModel('story');
        $this->loadModel('release');
        $this->loadModel('tree');
        $this->loadModel('user');

        /* Get all products, if no, goto the create page. */
        $this->products = $this->product->getPairs();
        if(empty($this->products) and strpos('create|view', $this->methodName) === false) $this->locate($this->createLink('product', 'create'));
        $this->view->products = $this->products;
    }

    /**
     * Index page, to browse.
     * 
     * @access public
     * @return void
     */
    public function index()
    {
        $this->locate($this->createLink($this->moduleName, 'browse'));
    }

    /**
     * Browse a product.
     * 
     * @param  int    $productID 
     * @param  string $browseType 
     * @param  int    $param 
     * @param  string $orderBy 
     * @param  int    $recTotal 
     * @param  int    $recPerPage 
     * @param  int    $pageID 
     * @access public
     * @return void
     */
    public function browse($productID = 0, $browseType = 'byModule', $param = 0, $orderBy = 'id_desc', $recTotal = 0, $recPerPage = 20, $pageID = 1)
    {
        /* Lower browse type. */
        $browseType = strtolower($browseType);

        /* Save session. */
        $this->session->set('storyList',   $this->app->getURI(true));
        $this->session->set('productList', $this->app->getURI(true));

        /* Set product, module and query. */
        $productID = $this->product->saveState($productID, $this->products);
        $moduleID  = ($browseType == 'bymodule') ? (int)$param : 0;
        $queryID   = ($browseType == 'bysearch') ? (int)$param : 0;

        /* Has access privilege?. */
        if(!$this->product->checkPriv($this->product->getById($productID)))
        {
            echo(js::alert($this->lang->product->accessDenied));
            die(js::locate('back'));
        }

        /* Set menu. */
        $this->product->setMenu($this->products, $productID);

        /* Set header and position. */
        $this->view->header->title = $this->lang->product->index . $this->lang->colon . $this->products[$productID];
        $this->view->position[]    = $this->products[$productID];

        /* Load pager. */
        $this->app->loadClass('pager', $static = true);
        $pager = new pager($recTotal, $recPerPage, $pageID);

        /* Get stories. */
        $stories = array();
        if($browseType == 'all')
        {
            $stories = $this->story->getProductStories($productID, 0, 'all', $orderBy, $pager);
        }
        elseif($browseType == 'bymodule')
        {
            $childModuleIds = $this->tree->getAllChildID($moduleID);
            $stories = $this->story->getProductStories($productID, $childModuleIds, 'all', $orderBy, $pager);
        }
        elseif($browseType == 'bysearch')
        {
            if($queryID)
            {
                $query = $this->loadModel('search')->getQuery($queryID);
                if($query)
                {
                    $this->session->set('storyQuery', $query->sql);
                    $this->session->set('storyForm', $query->form);
                }
                else
                {
                    $this->session->set('storyQuery', ' 1 = 1');
                }
            }
            else
            {
                if($this->session->storyQuery == false) $this->session->set('storyQuery', ' 1 = 1');
            }

            $stories = $this->story->getByQuery($productID, $this->session->storyQuery, $orderBy, $pager);
        }

        /* Build search form. */
        $this->config->product->search['actionURL'] = $this->createLink('product', 'browse', "productID=$productID&browseType=bySearch&queryID=myQueryID");
        $this->config->product->search['queryID']   = $queryID;
        $this->config->product->search['params']['plan']['values'] = $this->loadModel('productplan')->getPairs($productID);
        $this->view->searchForm = $this->fetch('search', 'buildForm', $this->config->product->search);

        $this->view->productID     = $productID;
        $this->view->productName   = $this->products[$productID];
        $this->view->moduleID      = $moduleID;
        $this->view->stories       = $stories;
        $this->view->moduleTree    = $this->tree->getTreeMenu($productID, $viewType = 'story', $startModuleID = 0, array('treeModel', 'createStoryLink'));
        $this->view->parentModules = $this->tree->getParents($moduleID);
        $this->view->pager         = $pager;
        $this->view->users         = $this->user->getPairs('noletter');
        $this->view->orderBy       = $orderBy;
        $this->view->browseType    = $browseType;
        $this->view->moduleID      = $moduleID;
        $this->view->treeClass     = $browseType == 'bymodule' ? '' : 'hidden';

        $this->display();
    }

    /**
     * Create a product. 
     * 
     * @access public
     * @return void
     */
    public function create()
    {
        if(!empty($_POST))
        {
            $productID = $this->product->create();
            if(dao::isError()) die(js::error(dao::getError()));
            $this->loadModel('action')->create('product', $productID, 'opened');
            die(js::locate($this->createLink($this->moduleName, 'browse', "productID=$productID"), 'parent'));
        }

        $this->product->setMenu($this->products, '');

        $this->view->header->title = $this->lang->product->create;
        $this->view->position[]    = $this->view->header->title;
        $this->view->groups        = $this->loadModel('group')->getPairs();
        $this->view->users         = $this->loadModel('user')->getPairs();
        $this->display();
    }

    /**
     * Edit a product.
     * 
     * @param  int    $productID 
     * @access public
     * @return void
     */
    public function edit($productID)
    {
        if(!empty($_POST))
        {
            $changes = $this->product->update($productID); 
            if(dao::isError()) die(js::error(dao::getError()));
            if($changes)
            {
                $actionID = $this->loadModel('action')->create('product', $productID, 'edited');
                $this->action->logHistory($actionID, $changes);
            }
            die(js::locate(inlink('view', "product=$productID"), 'parent'));
        }

        $this->product->setMenu($this->products, $productID);

        $product = $this->dao->findById($productID)->from(TABLE_PRODUCT)->fetch();
        $this->view->header->title = $this->lang->product->edit . $this->lang->colon . $product->name;
        $this->view->position[]    = html::a($this->createLink($this->moduleName, 'browse'), $product->name);
        $this->view->position[]    = $this->lang->product->edit;
        $this->view->product       = $product;
        $this->view->groups        = $this->loadModel('group')->getPairs();
        $this->view->users         = $this->loadModel('user')->getPairs();

        $this->display();
    }

    /**
     * View a product.
     * 
     * @param  int    $productID 
     * @access public
     * @return void
     */
    public function view($productID)
    {
        $this->product->setMenu($this->products, $productID);

        $product = $this->dao->findById($productID)->from(TABLE_PRODUCT)->fetch();
        if(!$product) die(js::error($this->lang->notFound) . js::locate('back'));

        $this->view->header->title = $this->lang->product->view . $this->lang->colon . $product->name;
        $this->view->position[]    = html::a($this->createLink($this->moduleName, 'browse'), $product->name);
        $this->view->position[]    = $this->lang->product->view;
        $this->view->product       = $product;
        $this->view->actions       = $this->loadModel('action')->getList('product', $productID);
        $this->view->users         = $this->user->getPairs('noletter');
        $this->view->groups        = $this->loadModel('group')->getPairs();

        $this->display();
    }

    /**
     * Delete a product.
     * 
     * @param  int    $productID 
     * @param  string $confirm    yes|no
     * @access public
     * @return void
     */
    public function delete($productID, $confirm = 'no')
    {
        if($confirm == 'no')
        {
            die(js::confirm($this->lang->product->confirmDelete, $this->createLink('product', 'delete', "productID=$productID&confirm=yes")));
        }
        else
        {
            $this->product->delete(TABLE_PRODUCT, $productID);
            $this->session->set('product', '');     // 清除session。
            die(js::locate($this->createLink('product', 'browse'), 'parent'));
        }
    }

    /**
     * Docs of a product.
     * 
     * @param  int    $productID 
     * @access public
     * @return void
     */
    public function doc($productID)
    {
        $this->product->setMenu($this->products, $productID);
        $this->session->set('docList', $this->app->getURI(true));

        $product = $this->dao->findById($productID)->from(TABLE_PRODUCT)->fetch();
        $this->view->header->title = $this->lang->product->doc;
        $this->view->position[]    = html::a($this->createLink($this->moduleName, 'browse'), $product->name);
        $this->view->position[]    = $this->lang->product->doc;
        $this->view->product       = $product;
        $this->view->docs          = $this->loadModel('doc')->getProductDocs($productID);
        $this->view->users         = $this->loadModel('user')->getPairs('noletter');
        $this->display();
    }

    /**
     * Road map of a product. 
     * 
     * @param  int    $productID 
     * @access public
     * @return void
     */
    public function roadmap($productID)
    {
        $this->product->setMenu($this->products, $productID);

        $this->session->set('releaseList',     $this->app->getURI(true));
        $this->session->set('productPlanList', $this->app->getURI(true));

        $product = $this->dao->findById($productID)->from(TABLE_PRODUCT)->fetch();
        $this->view->header->title = $this->lang->product->roadmap;
        $this->view->position[]    = html::a($this->createLink($this->moduleName, 'browse'), $product->name);
        $this->view->position[]    = $this->lang->product->roadmap;
        $this->view->product       = $product;
        $this->view->roadmaps      = $this->product->getRoadmap($productID);

        $this->display();
    }

    /**
     * AJAX: get projects of a product in html select.
     * 
     * @param  int    $productID 
     * @param  int    $projectID 
     * @access public
     * @return void
     */
    public function ajaxGetProjects($productID, $projectID = 0)
    {
        $projects = $this->product->getProjectPairs($productID);
        die(html::select('project', $projects, $projectID, 'onchange=loadProjectRelated(this.value)'));
    }

    /**
     * AJAX: get plans of a product in html select. 
     * 
     * @param  int    $productID 
     * @param  int    $planID 
     * @access public
     * @return void
     */
    public function ajaxGetPlans($productID, $planID = 0)
    {
        $plans = $this->loadModel('productplan')->getPairs($productID);
        die(html::select('plan', $plans, $planID));
    }
}
