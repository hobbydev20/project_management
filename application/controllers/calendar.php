<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Calendar extends MY_Controller {
               
	function __construct()
	{
		parent::__construct();
		$access = FALSE;
		if($this->client){	
			if($this->input->cookie('fc2_link') != ""){
					$link = $this->input->cookie('fc2_link');
					$link = str_replace("/tickets/", "/ctickets/", $link);
					redirect($link);
			}else{
				redirect('cprojects');
			}
			
		}elseif($this->user){
			foreach ($this->view_data['menu'] as $key => $value) { 
				if($value->link == "calendar"){ $access = TRUE;}
			}
			//if(!$access){redirect('login');}
		}else{
			redirect('login');
		}
		$this->view_data['submenu'] = array(
				 		$this->lang->line('application_all') => 'projects/filter/all',
				 		$this->lang->line('application_open') => 'projects/filter/open',
				 		$this->lang->line('application_closed') => 'projects/filter/closed'
				 		);	
		$this->load->database();
		
	}	
	function index()
	{

		if($this->user->admin == 0){ 
			$comp_array = array();
			$thisUserHasNoCompanies = (array) $this->user->companies;
					if(!empty($thisUserHasNoCompanies)){
				foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
				}
				$projects_by_client_admin = Project::find('all', array('conditions' => array('company_id in (?)', $comp_array)));

					//merge projects by client admin and assigned to projects
					$result = array_merge( $projects_by_client_admin, $this->user->projects );
					//duplicate objects will be removed
					$result = array_map("unserialize", array_unique(array_map("serialize", $result)));
					//array is sorted on the bases of id
					sort( $result );

					$projects = $result;
			}else{
				$projects = $this->user->projects;
			}
		}else{
			$projects = Project::all();
		}
		$project_events = "";
		foreach ($projects as $value) {
			$descr = preg_replace( "/\r|\n/", "", $value->description );
			$project_events .= "{
                          title: '".$this->lang->line('application_project').": ".addslashes($value->name)."',
                          start: '".$value->start."',
                          end: '".$value->end."',
                          url: '".base_url()."projects/view/".$value->id."',
                          className: 'project-event',
                          description: '".addslashes($descr)."'
                      },";
		}

		//events
		$events = Event::all();
		
		$event_list = "";
		foreach ($events as $value) {
			$event_list .= "{
                          title: '".addslashes($value->title)."',
                          start: '".$value->start."',
                          end: '".$value->end."',
                          url: '".base_url()."calendar/edit_event/".$value->id."',
                          className: '".$value->classname."',
                          modal: 'true',
                          description: '".addslashes(preg_replace( "/\r|\n/", "", $value->description))."',

                      },";
		}

		$this->view_data['core_settings'] = Setting::first();
		$this->view_data['project_events'] = $project_events;
		$this->view_data['events_list'] = $event_list;
		$this->setTitle('Work');
		$this->content_view = 'calendar/full';
		
	}

	function create(){
		if($_POST){
			unset($_POST['send']);
			$_POST['title'] = htmlspecialchars($_POST['title']);
			$_POST['start'] = new DateTime($_POST['start']);
			$_POST['start'] = $_POST['start']->format('Y-m-d H:i');
			$_POST['end'] = new DateTime($_POST['end']);
			$_POST['end'] = $_POST['end']->format('Y-m-d H:i');
			$_POST['description'] = htmlspecialchars($_POST['description']); 
			$_POST['user_id'] = $this->user->id;
			$Event = Event::create($_POST);
       		if(!$Event){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_create_event_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_create_event_success'));}
			redirect('calendar');
			
		}else
		{
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_create_event');
			$this->view_data['form_action'] = 'calendar/create';
			$this->content_view = 'calendar/_event';
		}	
	}

	function edit_event($id = FALSE){
		if($_POST){
			unset($_POST['send']);
			$event = Event::find_by_id($_POST['id']);
			unset($_POST['id']);
			$_POST['title'] = htmlspecialchars($_POST['title']);
			$_POST['start'] = new DateTime($_POST['start']);
			$_POST['start'] = $_POST['start']->format('Y-m-d H:i');
			$_POST['end'] = new DateTime($_POST['end']);
			$_POST['end'] = $_POST['end']->format('Y-m-d H:i');
			$_POST['description'] = htmlspecialchars($_POST['description']);
			$event = $event->update_attributes($_POST);
       		if(!$event){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_create_event_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_create_event_success'));}
			redirect('calendar');
			
		}else
		{
			$this->view_data['event'] = Event::find_by_id($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_update_event');
			$this->view_data['form_action'] = 'calendar/edit_event';
			$this->content_view = 'calendar/_event';
		}	
	}

	function delete($id = FALSE){
		$event = Event::find_by_id($id);
		$event->delete();
		if(!$event){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_delete_event_error'));}
       	else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_delete_event_success'));}
		redirect('calendar');
	}

}