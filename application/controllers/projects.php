<?php if ( ! defined( 'BASEPATH' ) ) {
	exit( 'No direct script access allowed' );
}

class Projects extends MY_Controller
{

	/** @var  projecttasks */
	public $projecttasks;

	function __construct()
	{
		parent::__construct();
		$access = false;
		if ( $this->client ) {
			$this->view_data['invoice_access'] = FALSE;
			foreach ($this->view_data['menu'] as $key => $value) { 
				if($value->link == "cinvoices"){ $this->view_data['invoice_access'] = TRUE;}
				if($value->link == "projects"){ $access = TRUE;}
			}
			if(!$access && !empty($this->view_data['menu'][0])){
				redirect($this->view_data['menu'][0]->link);
			}elseif(empty($this->view_data['menu'][0])){
				$this->view_data['error'] = "true";
				$this->session->set_flashdata('message', 'error: You have no access to any modules!');
				redirect('login');
			}

		} elseif ( $this->user ) {
			$this->view_data['invoice_access'] = false;
			foreach ( $this->view_data['menu'] as $key => $value ) {
				if ( $value->link == "invoices" ) {
					$this->view_data['invoice_access'] = true;
				}
				if ( $value->link == "projects" ) {
					$access = true;
				}
			}
			if ( ! $access ) {
				redirect( 'login' );
			}
		} else {
			redirect( 'login' );
		}
		$this->view_data['submenu'] = array(
			$this->lang->line( 'application_all' )    => 'projects/filter/all',
			$this->lang->line( 'application_open' )   => 'projects/filter/open',
			$this->lang->line( 'application_closed' ) => 'projects/filter/closed'
		);


	}

	function index1()
	{
		$options = array( 'conditions' => 'progress < 100' );
		if ( $this->user->admin == 0 ) {
			$comp_array             = array();
			$thisUserHasNoCompanies = (array) $this->user->companies;
			if ( ! empty( $thisUserHasNoCompanies ) ) {
				foreach ( $this->user->companies as $value ) {
					array_push( $comp_array, $value->id );
				}
				$projects_by_client_admin = Project::find( 'all', array(
					'conditions' => array(
						'progress < ? AND company_id in (?)',
						'100',
						$comp_array
					)
				) );

				//merge projects by client admin and assigned to projects
				$result = array_merge( $projects_by_client_admin, $this->user->projects );
				//duplicate objects will be removed
				$result = array_map( "unserialize", array_unique( array_map( "serialize", $result ) ) );
				//array is sorted on the bases of id
				sort( $result );

				$this->view_data['project'] = $result;
			} else {
				$this->view_data['project'] = $this->user->projects;
			}
		} else {
			$this->view_data['project'] = Project::all( $options );
		}
		$this->content_view                         = 'projects/all';
		$this->view_data['projects_assigned_to_me'] = ProjectHasWorker::find_by_sql( 'select count(distinct(projects.id)) AS "amount" FROM projects, project_has_workers WHERE projects.progress != "100" AND (projects.id = project_has_workers.project_id AND project_has_workers.user_id = "' . $this->user->id . '") ' );
		$this->view_data['tasks_assigned_to_me']    = ProjectHasTask::count( array( 'conditions' => 'user_id = ' . $this->user->id . ' and status = "open"' ) );
		$this->view_data['isAdmin']                 = $this->user->admin;

		$now                                          = time();
		$beginning_of_week                            = strtotime( 'last Monday', $now ); // BEGINNING of the week
		$end_of_week                                  = strtotime( 'next Sunday', $now ) + 86400; // END of the last day of the week
		$this->view_data['projects_opened_this_week'] = Project::find_by_sql( 'select count(id) AS "amount", DATE_FORMAT(FROM_UNIXTIME(`datetime`), "%w") AS "date_day", DATE_FORMAT(FROM_UNIXTIME(`datetime`), "%Y-%m-%d") AS "date_formatted" from projects where datetime >= "' . $beginning_of_week . '" AND datetime <= "' . $end_of_week . '" Group By date_formatted, `date_day`' );

	}

	function index() {
		$this->setTitle("Work");
		$this->content_view = "projects/work";
	}

	function data()
	{
		$condition = isset($_GET['status']) ? strtolower($_GET['status']) : 'all';
		$options = array( 'conditions' => 'progress < 100' );

		if($this->user){
			if ( $this->user->admin == 0 ) {
				switch ( $condition ) {
					case 'open':
						$options = 'progress < 100';
						break;
					case 'closed':
						$options = 'progress = 100';
						break;
					case 'all':
						$options = '(progress = 100 OR progress < 100)';
						break;
				}

				$project_array = array();
				if ( $this->user->projects ) {
					foreach ( $this->user->projects as $value ) {
						array_push( $project_array, $value->id );
					}
				}

				$thisUserHasNoCompanies = (array) $this->user->companies;
				if ( ! empty( $thisUserHasNoCompanies ) ) {
					$comp_array = array();
					foreach ( $this->user->companies as $value ) {
						array_push( $comp_array, $value->id );
					}


					$projects_by_client_admin = Project::find( 'all', array(
						'conditions' => array(
							$options . ' AND company_id in (?)',
							$comp_array
						)
					) );

					//merge projects by client admin and assigned to projects
					$result = array_merge( $projects_by_client_admin, $this->user->projects );
					//duplicate objects will be removed
					$result = array_map( "unserialize", array_unique( array_map( "serialize", $result ) ) );
					//array is sorted on the bases of id
					sort( $result );

					$projects = $result;
				} else {
					$projects = Project::find( 'all', array(
						'conditions' => array(
							$options . ' AND id in (?)',
							$project_array
						)
					) );
				}
			} else {
				switch ( $condition ) {
					case 'open':
						$options = array( 'conditions' => 'progress < 100' );
						break;
					case 'closed':
						$options = array( 'conditions' => 'progress = 100' );
						break;
					case 'all':
						$options = array( 'conditions' => 'progress = 100 OR progress < 100' );
						break;
				}
				$projects = Project::all( $options );
			}

			$projects = array_map( function ( $project ) {

				$attributes = $project->attributes();

				if ( ! empty( $attributes['company_id'] ) ) {
					$attributes['company'] = Company::find( $attributes['company_id'] )->attributes();
				}

				if($project->start)
					$attributes['start_human'] = date('M y', strtotime($project->start));

				$attributes['total_tasks'] = ProjectHasTask::count(array('conditions' => array('project_id = ?', $project->id)));
				$attributes['done_tasks'] = ProjectHasTask::count(array('conditions' => array('project_id = ? AND status = "done"', $project->id)));
				$attributes['favourite'] = true;

				$diff = date_diff(date_create($project->end), date_create(date('Y-m-d h:i:s')));
				$due_in = "";

				if($diff->invert == 0) {
					$due_in .= "Overdue by ";
				} else {
					$due_in .= "Due in ";
				}

				if($diff->y > 0)
					$due_in .= "{$diff->y} years";
				else if($diff->m > 0)
					$due_in .= "{$diff->m} months";
				else if($diff->d > 0)
					$due_in .= "{$diff->d} days";

				$attributes['due_in'] = $due_in;
				if(isset($project->project_has_workers)){
					$attributes['workers'] = array_map(function($worker) {
						$attributes = $worker->attributes();
						$attributes['worker'] = $worker->user->attributes();
						$attributes['worker']['userpic'] = get_user_pic($attributes['worker']['userpic']);
						return $attributes;
					}, $project->project_has_workers);
				}else{
					$attributes['workers'] = array();
				}
				$attributes['tracking'] = ProjectUserTimeTracking::isTracking($project->id, $this->user->id);

				return $attributes;
			}, $projects );

			$sticky_projects = [];
			foreach ($projects as $project) {
				if ($project['sticky'] =='1') $sticky_projects[] = $project;
			}
			foreach ($projects as $project) {
				if ($project['sticky'] =='0') $sticky_projects[] = $project;
			}

			echo json_encode( [
				                  'status'   => true,
				                  'projects' => $sticky_projects
			                  ] );

			die();
		}elseif ($this->client) {
			switch ( $condition ) {
				case 'open':
					$options = array( 'conditions' => 'progress < 100' );
					break;
				case 'closed':
					$options = array( 'conditions' => 'progress = 100' );
					break;
				case 'all':
					$options = array( 'conditions' => 'progress = 100 OR progress < 100' );
					break;
			}
			$projects = Project::find('all',array('conditions' => array('company_id=?',$this->client->company->id)));

			$projects = array_map( function ( $project ) {

				$attributes = $project->attributes();

				if ( ! empty( $attributes['company_id'] ) ) {
					$attributes['company'] = Company::find( $attributes['company_id'] )->attributes();
				}

				if($project->start)
					$attributes['start_human'] = date('M y', strtotime($project->start));

				$attributes['total_tasks'] = ProjectHasTask::count(array('conditions' => array('project_id = ?', $project->id)));
				$attributes['done_tasks'] = ProjectHasTask::count(array('conditions' => array('project_id = ? AND status = "done"', $project->id)));
				$attributes['favourite'] = true;

				$diff = date_diff(date_create($project->end), date_create(date('Y-m-d h:i:s')));
				$due_in = "";

				if($diff->invert == 0) {
					$due_in .= "Overdue by ";
				} else {
					$due_in .= "Due in ";
				}

				if($diff->y > 0)
					$due_in .= "{$diff->y} years";
				else if($diff->m > 0)
					$due_in .= "{$diff->m} months";
				else if($diff->d > 0)
					$due_in .= "{$diff->d} days";
				$attributes['due_in'] = $due_in;

				if(isset($project->project_has_workers)){
					$attributes['workers'] = array_map(function($worker) {
						$attributes = $worker->attributes();
						$attributes['worker'] = $worker->user->attributes();
						$attributes['worker']['userpic'] = get_user_pic($attributes['worker']['userpic']);
						return $attributes;
					}, $project->project_has_workers);
				}else{
					$attributes['workers'] = array();
				}
				
				
				$attributes['tracking'] = ProjectUserTimeTracking::isTracking($project->id, $this->client->id);

				return $attributes;
				}, $projects );

			$sticky_projects = [];
			foreach ($projects as $project) {
				if ($project['sticky'] =='1') $sticky_projects[] = $project;
			}
			foreach ($projects as $project) {
				if ($project['sticky'] =='0') $sticky_projects[] = $project;
			}

			echo json_encode( [
				                  'status'   => true,
				                  'projects' => $sticky_projects
			                  ] );

			die();
		}
		

		
	}

	function filter( $condition )
	{
		$options = array( 'conditions' => 'progress < 100' );
		if ( $this->user->admin == 0 ) {
			switch ( $condition ) {
				case 'open':
					$options = 'progress < 100';
					break;
				case 'closed':
					$options = 'progress = 100';
					break;
				case 'all':
					$options = '(progress = 100 OR progress < 100)';
					break;
			}

			$project_array = array();
			if ( $this->user->projects ) {
				foreach ( $this->user->projects as $value ) {
					array_push( $project_array, $value->id );
				}
			}

			$thisUserHasNoCompanies = (array) $this->user->companies;
			if ( ! empty( $thisUserHasNoCompanies ) ) {
				$comp_array = array();
				foreach ( $this->user->companies as $value ) {
					array_push( $comp_array, $value->id );
				}


				$projects_by_client_admin = Project::find( 'all', array(
					'conditions' => array(
						$options . ' AND company_id in (?)',
						$comp_array
					)
				) );

				//merge projects by client admin and assigned to projects
				$result = array_merge( $projects_by_client_admin, $this->user->projects );
				//duplicate objects will be removed
				$result = array_map( "unserialize", array_unique( array_map( "serialize", $result ) ) );
				//array is sorted on the bases of id
				sort( $result );

				$this->view_data['project'] = $result;
			} else {
				$this->view_data['project'] = Project::find( 'all', array(
					'conditions' => array(
						$options . ' AND id in (?)',
						$project_array
					)
				) );
			}
		} else {
			switch ( $condition ) {
				case 'open':
					$options = array( 'conditions' => 'progress < 100' );
					break;
				case 'closed':
					$options = array( 'conditions' => 'progress = 100' );
					break;
				case 'all':
					$options = array( 'conditions' => 'progress = 100 OR progress < 100' );
					break;
			}
			$this->view_data['project'] = Project::all( $options );
		}


		$this->content_view = 'projects/all';

		$this->view_data['projects_assigned_to_me'] = ProjectHasWorker::find_by_sql( 'select count(distinct(projects.id)) AS "amount" FROM projects, project_has_workers WHERE projects.progress != "100" AND (projects.id = project_has_workers.project_id AND project_has_workers.user_id = "' . $this->user->id . '") ' );
		$this->view_data['tasks_assigned_to_me']    = ProjectHasTask::count( array( 'conditions' => 'user_id = ' . $this->user->id . ' and status = "open"' ) );

		$now                                          = time();
		$beginning_of_week                            = strtotime( 'last Monday', $now ); // BEGINNING of the week
		$end_of_week                                  = strtotime( 'next Sunday', $now ) + 86400; // END of the last day of the week
		$this->view_data['projects_opened_this_week'] = Project::find_by_sql( 'select count(id) AS "amount", DATE_FORMAT(FROM_UNIXTIME(`datetime`), "%w") AS "date_day", DATE_FORMAT(FROM_UNIXTIME(`datetime`), "%Y-%m-%d") AS "date_formatted" from projects where datetime >= "' . $beginning_of_week . '" AND datetime <= "' . $end_of_week . '" Group By date_formatted, `date_day`' );

	}

	function create()
	{
		if ( $_POST ) {
			unset( $_POST['send'] );
			$_POST['datetime'] = time();
			$_POST             = array_map( 'htmlspecialchars', $_POST );
			unset( $_POST['files'] );
			$isConnectSLK = false;
			if ( isset( $_POST['connect_slk_channel'] ) && $_POST['connect_slk_channel'] == '1' ) {
				$isConnectSLK = true;
				unset( $_POST['connect_slk_channel'] );
			}

			/* @var bool|Project $project An object of Project model */
			$project               = Project::create( $_POST );
			$new_project_reference = $_POST['reference'] + 1;
			$project_reference     = Setting::first();
			$project_reference->update_attributes( array( 'project_reference' => $new_project_reference ) );
			if ( ! $project ) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_create_project_error' ) );
			} else {
				$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_create_project_success' ) );
				$attributes = array( 'project_id' => $project->id, 'user_id' => $this->user->id );
				ProjectHasWorker::create( $attributes );
			}

			if ( $project && $isConnectSLK ) {
				$this->setSlackChannelFor( $project, $_POST['name'], $isConnectSLK );
			}
			redirect( 'projects' );
		} else {
			if($this->user){
				if ( $this->user->admin == 0 ) {
					$this->view_data['companies'] = $this->user->companies;
				} else {
					$this->view_data['companies'] = Company::find( 'all', array(
						'conditions' => array(
							'inactive=?',
							'0'
						)
					) );
				}
				$this->view_data['isLinkedSlack']   = count( $this->user->slack_links ) > 0;
				$this->view_data['isLinkedChannel'] = true;
			}elseif ($this->client) {
				$this->view_data['companies'] = array($this->client->company);
				$this->view_data['isLinkedSlack']   = false;
				$this->view_data['isLinkedChannel'] = false;
			}
			
			$last_reference = Project::find(array('order' => 'reference desc'));
			
			if (isset($last_reference)){
				$this->view_data['reference'] = $last_reference->reference + 1;
			} else {
				$this->view_data['reference'] = 51001;
			}

			$this->view_data['next_reference']  = Project::last();
			$this->view_data['category_list']   = Project::get_categories();
			$this->theme_view                   = 'modal';
			$this->view_data['title']           = $this->lang->line( 'application_create_new_project' );
			$this->view_data['form_action']     = 'projects/create';			
			$this->content_view                 = 'projects/_project';
		}
	}

	function export()
	{

		if ( $_POST ) {
			$parsedAccountUrlPrefix = $_SESSION['accountUrlPrefix'];

			$databaseName = $parsedAccountUrlPrefix . '_' . ENVIRONMENT;

			// @var CI_DB_mysql_driver $primaryDatabase /
			$primaryDatabase = $this->load->database( $databaseName, true );

			$params = [
				'databaseName'    => $databaseName,
				'primaryDatabase' => $primaryDatabase
			];

			$this->load->library( 'projecttasks', $params );
			$projectIdList = ( isset( $_POST['projects'] ) ) ? $_POST['projects'] : [];

			$projectTasks = $this->projecttasks->getProjectTasksForSelectProjects( $projectIdList );

			$tsv = $this->projecttasks->formatTsv( $projectTasks );

			header( "Content-type: text/tab-separated-values" );
			header( "Content-Disposition: attachment; filename=projectTaskList.tsv" );
			header( "Pragma: no-cache" );
			header( "Expires: 0" );
			echo $tsv;
			die();
		} else {

			$parsedAccountUrlPrefix = $_SESSION['accountUrlPrefix'];

			$databaseName = $parsedAccountUrlPrefix . '_' . ENVIRONMENT;

			// @var CI_DB_mysql_driver $primaryDatabase /
			$primaryDatabase = $this->load->database( $databaseName, true );

			$params = [
				'databaseName'    => $databaseName,
				'primaryDatabase' => $primaryDatabase
			];

			$this->load->library( 'projecttasks', $params );

			$projects = $this->projecttasks->getProjects();

			$this->theme_view               = 'modal';
			$this->view_data['title']       = $this->lang->line( 'application_export' );
			$this->view_data['form_action'] = 'projects/export';
			$this->view_data['projects']    = $projects;
			$this->content_view             = 'projects/_export';
		}
	}

	function update( $id = false )
	{
		if ( $_POST ) {
			unset( $_POST['send'] );
			$id = $_POST['id'];
			unset( $_POST['files'] );
			$_POST = array_map( 'htmlspecialchars', $_POST );
			if ( ! isset( $_POST["progress_calc"] ) ) {
				$_POST["progress_calc"] = 0;
			}
			if ( $this->user->admin == 1 ) {
				if ( ! isset( $_POST["hide_tasks"] ) ) {
					$_POST["hide_tasks"] = 0;
				}

			}
			if ( ! isset( $_POST["enable_client_tasks"] ) ) {
				$_POST["enable_client_tasks"] = 0;
			}
			$isConnectSLK = false;
			if ( isset( $_POST['connect_slk_channel'] ) && $_POST['connect_slk_channel'] == '1' ) {
				$isConnectSLK = true;
				unset( $_POST['connect_slk_channel'] );
			}

			/* @var bool|Project $project An object of Project model */
			$project = Project::find( $id );
			$project->update_attributes( $_POST );
			$channel = SlackLinkedChannel::getSlackChannelBy( $id, $this->user->id );

			if ( $channel == null ) {
				$this->setSlackChannelFor( $project, $_POST['name'], $isConnectSLK );
			}
			$project = Project::find( $id );
			$channel = SlackLinkedChannel::getSlackChannelBy( $id, $this->user->id );
			if ( $channel ) {
				$channel->connection_flag = $isConnectSLK;
				$channel->save();
			}

			if ( ! $project ) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_project_error' ) );
			} else {
				$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_project_success' ) );
			}
			//redirect( 'projects/view/' . $id . '/tasks' );
			redirect( '/projects' );
		} else {
			if($this->user){
				if ( $this->user->admin == 0 ) {
					$this->view_data['companies'] = $this->user->companies;
				} else {
					$this->view_data['companies'] = Company::find( 'all', array(
						'conditions' => array(
							'inactive=?',
							'0'
						)
					) );
				}
				$project                            = Project::find( $id );
				$channel                            = SlackLinkedChannel::getSlackChannelBy( $id, $this->user->id );
				$this->view_data['project']         = $project;
				$this->theme_view                   = 'modal';
				$this->view_data['title']           = $this->lang->line( 'application_edit_project' );
				$this->view_data['form_action']     = 'projects/update';
				$this->view_data['isLinkedSlack']   = count( $this->user->slack_links ) > 0;
				$this->view_data['isLinkedChannel'] = $channel != null && $channel->connection_flag == 1;
				$this->content_view                 = 'projects/_project';
			}elseif($this->client){
				$this->view_data['company'] = $this->client->company;

				$project                            = Project::find( $id );
				//$channel                            = SlackLinkedChannel::getSlackChannelBy( $id, $this->client->id );
				$this->view_data['project']         = $project;
				$this->theme_view                   = 'modal';
				$this->view_data['title']           = $this->lang->line( 'application_edit_project' );
				$this->view_data['form_action']     = 'projects/update';
				//$this->view_data['isLinkedSlack']   = count( $this->client->slack_links ) > 0;
				//$this->view_data['isLinkedChannel'] = $channel != null && $channel->connection_flag == 1;
				$this->view_data['isLinkedSlack'] = false;
				$this->view_data['isLinkedChannel'] = false;
				$this->content_view                 = 'projects/_project';
			}
			
			
		}
	}


	/**
	 * @param bool|Project $project
	 * @param string $project_name
	 * @param bool $isConnectSLK
	 *
	 * @returns void
	 */
	function setSlackChannelFor( $project = null, $project_name = '', $isConnectSLK = false )
	{
		$this->load->helper( 'slack' );
		// Setup the Slack handler
		$link  = SlackLink::getSlackLatestLink( $this->user->id );
		$str   = $link ? json_encode( $link->to_array() ) : false;
		$slack = initialize_slack_interface( $str );

		$temp_name   = strtolower( $project_name );
		$temp_name   = str_replace( " ", "-", $temp_name );
		$temp_name   = substr( $temp_name, 0, 10 );
		$channelName = preg_replace( "/[^a-z-_]/", "", SLACK_CHANNEL_PREFIX . $temp_name );
		$result      = slack_connect_channel( $slack, $channelName );
		if ( $result['ok'] == true ) {
			$attributes = array(
				'project_id'      => $project->id,
				'user_id'         => $this->user->id,
				'slack_link_id'   => $link->id,
				'connection_flag' => $isConnectSLK,
				'channel_name'    => $result['channel']['name']
			);
			SlackLinkedChannel::create( $attributes );
			slack_post_message( $slack, $channelName, 'A new connection for ' . $project_name . ' Project', false );

		} else {
			$this->session->set_flashdata( 'slack_message', 'error:' . 'Creating of Slack channel was failed' );
		}
	}

	function sortlist( $sort = false, $list = false )
	{
		if ( $sort ) {
			$sort       = explode( "-", $sort );
			$sortnumber = 1;
			foreach ( $sort as $value ) {
				$task = ProjectHasTask::find_by_id( $value );
				if ( $list != "task-list" ) {
					$task->milestone_order = $sortnumber;
				} else {
					$task->task_order = $sortnumber;
				}
				$task->save();
				$sortnumber = $sortnumber + 1;
			}
		}
		$this->theme_view = 'blank';
	}

	function sort_milestone_list( $sort = false, $list = false )
	{
		if ( $sort ) {
			$sort       = explode( "-", $sort );
			$sortnumber = 1;
			foreach ( $sort as $value ) {
				$task             = ProjectHasMilestone::find_by_id( $value );
				$task->orderindex = $sortnumber;
				$task->save();
				$sortnumber = $sortnumber + 1;
			}
		}
		$this->theme_view = 'blank';
	}

	function move_task_to_milestone( $taskId = false, $listId = false )
	{
		if ( $listId && $taskId ) {
			$task               = ProjectHasTask::find_by_id( $taskId );
			$task->milestone_id = $listId;
			$task->save();
		}
		$this->theme_view = 'blank';
	}

	function task_change_attribute()
	{
		if ( $_POST ) {
			$name          = $_POST["name"];
			$taskId        = $_POST["pk"];
			$value         = $_POST["value"];
			$task          = ProjectHasTask::find_by_id( $taskId );
			$task->{$name} = $value;
			$task->save();
		}
		$this->theme_view = 'blank';
	}

	function task_start_stop_timer( $taskId )
	{
		$task = ProjectHasTask::find_by_id( $taskId );
		if ( $task->tracking != 0 ) {
			$now              = time();
			$diff             = $now - $task->tracking;
			$timer_start      = $task->tracking;
			$task->time_spent = $task->time_spent + $diff;
			$task->tracking   = "";
			//add time to timesheet
			$attributes = array(
				'task_id'    => $task->id,
				'user_id'    => $task->user_id,
				'project_id' => $task->project_id,
				'client_id'  => 0,
				'time'       => $diff,
				'start'      => $timer_start,
				'end'        => $now
			);
			$timesheet  = ProjectHasTimesheet::create( $attributes );

		} else {
			$task->tracking = time();
		}
		$task->save();
		$this->theme_view = 'blank';

		if ( $task->tracking != 0 && $task->tracking != "" ) {
			$timertime = ( time() - $task->tracking ) + $task->time_spent;
			$state = "resume";
		} else {
			$timertime = ( $task->time_spent != 0 && $task->time_spent != "" ) ? $task->time_spent : 0;
			$state = "pause";
		}

		echo json_encode( [
			                  'status' => true,
			                  'data'   => [
				                  'tracking'  => $task->tracking,
				                  'timertime' => $timertime,
				                  'state'     => $state
			                  ]
		                  ] );
	}

	function get_milestone_list( $projectId )
	{
		$milestone_list = "";
		$project        = Project::find_by_id( $projectId );
		foreach ( $project->project_has_milestones as $value ) {
			$milestone_list .= '{value:' . $value->id . ', text: "' . $value->name . '"},';
		}
		echo $milestone_list;
		$this->theme_view = 'blank';
	}

	function copy( $id = false )
	{
		if ( $_POST ) {
			unset( $_POST['send'] );
			$id = $_POST['id'];
			unset( $_POST['id'] );
			$_POST['datetime'] = time();
			$_POST             = array_map( 'htmlspecialchars', $_POST );
			unset( $_POST['files'] );
			if ( isset( $_POST['tasks'] ) ) {
				unset( $_POST['tasks'] );
				$tasks = true;
			}

			$project               = Project::create( $_POST );
			$new_project_reference = $_POST['reference'] + 1;
			$project_reference     = Setting::first();
			$project_reference->update_attributes( array( 'project_reference' => $new_project_reference ) );

			if ( $tasks ) {
				unset( $_POST['tasks'] );
				$source_project = Project::find_by_id( $id );
				foreach ( $source_project->project_has_tasks as $row ) {
					$attributes = array(
						'project_id'  => $project->id,
						'name'        => $row->name,
						'user_id'     => '',
						'status'      => 'open',
						'public'      => $row->public,
						'datetime'    => $project->start,
						'due_date'    => $project->end,
						'description' => $row->description,
						'value'       => $row->value,
						'priority'    => $row->priority,

					);
					ProjectHasTask::create( $attributes );
				}

			}

			if ( ! $project ) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_create_project_error' ) );
			} else {
				$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_create_project_success' ) );
				$attributes = array( 'project_id' => $project->id, 'user_id' => $this->user->id );
				ProjectHasWorker::create( $attributes );
			}
			redirect( 'projects/view/' . $id . '/tasks' );
		} else {
			$this->view_data['companies']   = Company::find( 'all', array(
				'conditions' => array(
					'inactive=?',
					'0'
				)
			) );
			$this->view_data['project']     = Project::find( $id );
			$this->theme_view               = 'modal';
			$this->view_data['title']       = $this->lang->line( 'application_copy_project' );
			$this->view_data['form_action'] = 'projects/copy';
			$this->content_view             = 'projects/_copy';
		}
	}

	function assign( $id = false )
	{
		$this->load->helper( 'notification' );
		if ( $_POST ) {
			unset( $_POST['send'] );
			$id      = addslashes( $_POST['id'] );
			$project = Project::find_by_id( $id );
			if ( ! isset( $_POST["user_id"] ) ) {
				$_POST["user_id"] = array();
			}
			$query = array();
			foreach ( $project->project_has_workers as $key => $value ) {
				array_push( $query, $value->user_id );
			}

			$added   = array_diff( $_POST["user_id"], $query );
			$removed = array_diff( $query, $_POST["user_id"] );

			foreach ( $added as $value ) {
				$atributes = array( 'project_id' => $id, 'user_id' => $value );
				$worker    = ProjectHasWorker::create( $atributes );
				send_user_notification( $this->user, $worker->user->email, $this->lang->line( 'application_notification_project_assign_subject' ), $this->lang->line( 'application_notification_project_assign' ) . '<br><strong>' . $project->name . '</strong>', false, base_url() . 'projects/view/' . $id );
			}

			foreach ( $removed as $value ) {
				$atributes = array( 'project_id' => $id, 'user_id' => $value );
				$worker    = ProjectHasWorker::find( $atributes );
				$worker->delete();
			}

			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_project_success' ) );
			//redirect( 'projects/view/' . $id );
			redirect( 'projects/view/' . $id . '/team' );
		} else {
			$this->view_data['users']       = User::find( 'all', array(
				'conditions' => array(
					'status=?',
					'active'
				)
			) );
			$this->view_data['project']     = Project::find( $id );
			$this->theme_view               = 'modal';
			$this->view_data['title']       = $this->lang->line( 'application_assign_team_members' );
			$this->view_data['form_action'] = 'projects/assign';
			$this->content_view             = 'projects/_assign';
		}
	}

	function delete1( $id = false )
	{
		if ( $this->user->admin == 0 ) {
			$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_project_error' ) );
			redirect( 'projects' );
		}
		$project = Project::find( $id );
		$project->delete();
		$tasks    = ProjectHasTask::find( 'all', array( 'conditions' => array( 'project_id=?', $id ) ) );
		$toDelete = array();
		foreach ( $tasks as $value ) {
			array_push( $toDelete, $value->id );
		}
		ProjectHasTask::table()->delete( array( 'id' => $toDelete ) );
		$this->content_view = 'projects/all';
		if ( ! $project ) {
			$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_project_error' ) );
		} else {
			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_project_success' ) );
		}
		if ( isset( $view ) ) {
			redirect( 'projects/view/' . $id );
		} else {
			redirect( 'projects' );
		}
	}

	function delete( $id = false )
	{
		$deleted = false;
		if ( $this->user->admin == 0 ) {
			$deleted = false;
		} else {
			$project = Project::find( $id );
			$project->delete();
			$tasks    = ProjectHasTask::find( 'all', array( 'conditions' => array( 'project_id=?', $id ) ) );
			$toDelete = array();
			
			if ( count($tasks) > 0) {
				foreach ( $tasks as $value ) {
					array_push( $toDelete, $value->id );
				}
				ProjectHasTask::table()->delete( array( 'id' => $toDelete ) );	
			}

			$this->content_view = 'projects/all';
			if ( ! $project ) {
				$deleted = false;
			} else {
				$deleted = true;
			}
		}

		echo json_encode( [
			                  'status' => $deleted
		                  ] );
		die();
	}

	function timer_reset( $id = false )
	{
		$project = Project::find( $id );
		$attr    = array( 'time_spent' => '0' );
		$project->update_attributes( $attr );
		$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_timer_reset' ) );
		redirect( 'projects/view/' . $id );
	}

	function timer_set( $id = false )
	{
		if ( $_POST ) {
			$project   = Project::find_by_id( $_POST['id'] );
			$hours     = $_POST['hours'];
			$minutes   = $_POST['minutes'];
			$timespent = ( $hours * 60 * 60 ) + ( $minutes * 60 );
			$attr      = array( 'time_spent' => $timespent );
			$project->update_attributes( $attr );
			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_timer_set' ) );
			redirect( 'projects/view/' . $_POST['id'] );
		} else {
			$this->view_data['project']     = Project::find( $id );
			$this->theme_view               = 'modal';
			$this->view_data['title']       = $this->lang->line( 'application_timer_set' );
			$this->view_data['form_action'] = 'projects/timer_set';
			$this->content_view             = 'projects/_timer';
		}
	}

	function view1( $id = false, $taskId = false, $comment_guid = false )
	{
		$this->load->helper( 'file' );
		$this->view_data['submenu']              = array();
		$this->view_data['project']              = Project::find( $id );
		$this->view_data['go_to_taskID']         = $taskId;
		$this->view_data['first_project']        = Project::first();
		$this->view_data['last_project']         = Project::last();
		$this->view_data['project_has_invoices'] = Invoice::all( array(
			                                                         'conditions' => array(
				                                                         'project_id = ? AND estimate != ?',
				                                                         $id,
				                                                         1
			                                                         )
		                                                         ) );
		if ( ! isset( $this->view_data['project_has_invoices'] ) ) {
			$this->view_data['project_has_invoices'] = array();
		}
		$tasks                            = ProjectHasTask::count( array( 'conditions' => 'project_id = ' . $id ) );
		$this->view_data['alltasks']      = $tasks;
		$this->view_data['opentasks']     = ProjectHasTask::count( array(
			                                                           'conditions' => array(
				                                                           'status != ? AND project_id = ?',
				                                                           'done',
				                                                           $id
			                                                           )
		                                                           ) );
		$this->view_data['usercountall']  = User::count( array( 'conditions' => array( 'status = ?', 'active' ) ) );
		$this->view_data['usersassigned'] = ProjectHasWorker::count( array(
			                                                             'conditions' => array(
				                                                             'project_id = ?',
				                                                             $id
			                                                             )
		                                                             ) );

		$this->view_data['assigneduserspercent'] = round( $this->view_data['usersassigned'] / $this->view_data['usercountall'] * 100 );


		//Format statistic labels and values
		$this->view_data["labels"] = "";
		$this->view_data["line1"]  = "";
		$this->view_data["line2"]  = "";

		$daysOfWeek                         = getDatesOfWeek();
		$this->view_data['dueTasksStats']   = ProjectHasTask::getDueTaskStats( $id, $daysOfWeek[0], $daysOfWeek[6] );
		$this->view_data['startTasksStats'] = ProjectHasTask::getStartTaskStats( $id, $daysOfWeek[0], $daysOfWeek[6] );

		$history = ProjectChat::find( 'all', array(
			'conditions' => array( "project_id='" . $id . "'" ),
			'order'      => 'created desc',
			'limit'      => 100
		) );
		$newHist = [];
		foreach ( $history as $item ) {
			$chat = $item->to_array();

			if ( $item->from_external == 0 ) {
				$user = User::find( $chat['sender_id'] );
			} else {
				$user = User::find( $item->sender_id );
				if ( empty( $user ) ) {
					$link = SlackLink::find( 'first', array(
						'conditions' => array(
							"slack_id=? AND team_id=?",
							$item->slack_id,
							$item->team_id
						)
					) );
					if ( ! empty( $link->user_id ) ) {
						$user = User::find( $link->user_id );
					}
				}
			}
//            echo "<pre>"; print_r($user); echo "</pre>";
			$userpic_bot = '';
			if ( ! empty( $user ) ) {
				$username = $user->firstname . ' ' . $user->lastname;
				$userpic  = get_user_pic( $user->userpic, $user->email );
				if ( ! $userpic ) {
					$userpic_bot = strtoupper( substr( $user->firstname, 0, 1 ) . substr( $user->lastname, 0, 1 ) );
				}
			} else {
				$username = 'Unknown';
				$baseUrl  = 'https://spera-' . ENVIRONMENT . '.s3-us-west-2.amazonaws.com/' . $_SESSION['accountUrlPrefix'] . '/';
				$userpic  = $baseUrl . "files/media/no-pic.png";
			}
			$slackpic = '';
			if ( $item->from_external != 0 ) {
				$slackpic = "../../assets/blueline/img/slack_mark.png";
			}

			$phptime  = strtotime( $item->created );
			$viewtime = date( "Y-m-d H:i:s", $phptime );

			$chatItem = array(
				'avatar_url'   => $userpic,
				'avatar_str'   => $userpic_bot,
				'second_url'   => $slackpic,
				'user_name'    => $username,
				'is_me'        => ( ! empty( $user ) && $this->user->id == $user->id ),
				'chat_message' => $item->chat_message,
				'date_time'    => $viewtime
			);
			array_push( $newHist, $chatItem );
		}

		$myname    = $this->user->firstname . ' ' . $this->user->lastname;
		$mypic     = get_user_pic( $this->user->userpic, $this->user->email );
		$mypic_bot = false;
		if ( ! $mypic ) {
			$mypic_bot = strtoupper( substr( $this->user->firstname, 0, 1 ) . substr( $this->user->lastname, 0, 1 ) );
		}
		$myInfo = array(
			'avatar_url'   => $mypic,
			'avatar_str'   => $mypic_bot,
			'second_url'   => '',
			'user_name'    => $myname,
			'is_me'        => true,
			'chat_message' => '',
			'date_time'    => ''
		);

		$this->view_data['chatHistory'] = json_encode( $newHist );
		$this->view_data['myInfo']      = json_encode( $myInfo );

		foreach ( $daysOfWeek as $day ) {
			$counter  = "0";
			$counter2 = "0";
			foreach ( $this->view_data['dueTasksStats'] as $value ):
				if ( $value->due_date == $day ) {
					$counter = $value->tasksdue;
				}
			endforeach;
			foreach ( $this->view_data['startTasksStats'] as $value ):
				if ( $value->start_date == $day ) {
					$counter2 = $value->tasksdue;
				}
			endforeach;
			$this->view_data["labels"] .= '"' . $day . '"';
			$this->view_data["labels"] .= ',';
			$this->view_data["line1"]  .= $counter . ",";
			$this->view_data["line2"]  .= $counter2 . ",";

		}

		$this->view_data['time_days']       = round( ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) - human_to_unix( $this->view_data['project']->start . ' 00:00' ) ) / 3600 / 24 );
		$this->view_data['time_left']       = $this->view_data['time_days'];
		$this->view_data['timeleftpercent'] = 100;

		if ( human_to_unix( $this->view_data['project']->start . ' 00:00' ) < time() && human_to_unix( $this->view_data['project']->end . ' 00:00' ) > time() ) {
			$this->view_data['time_left']       = round( ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) - time() ) / 3600 / 24 );
			$this->view_data['timeleftpercent'] = $this->view_data['time_left'] / $this->view_data['time_days'] * 100;
		}
		if ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) < time() ) {
			$this->view_data['time_left']       = 0;
			$this->view_data['timeleftpercent'] = 0;
		}
		$this->view_data['allmytasks']            = ProjectHasTask::all( array(
			                                                                 'conditions' => array(
				                                                                 'project_id = ? AND user_id = ?',
				                                                                 $id,
				                                                                 $this->user->id
			                                                                 )
		                                                                 ) );
		$this->view_data['mytasks']               = ProjectHasTask::count( array(
			                                                                   'conditions' => array(
				                                                                   'status != ? AND project_id = ? AND user_id = ?',
				                                                                   'done',
				                                                                   $id,
				                                                                   $this->user->id
			                                                                   )
		                                                                   ) );
		$this->view_data['tasksWithoutMilestone'] = ProjectHasTask::find( 'all', array(
			'conditions' => array(
				'milestone_id = ? AND project_id = ? ',
				'0',
				$id
			)
		) );

		$tasks_done                  = ProjectHasTask::count( array(
			                                                      'conditions' => array(
				                                                      'status = ? AND project_id = ?',
				                                                      'done',
				                                                      $id
			                                                      )
		                                                      ) );
		$this->view_data['progress'] = $this->view_data['project']->progress;
		if ( $this->view_data['project']->progress_calc == 1 ) {
			if ( $tasks ) {
				@$this->view_data['progress'] = round( $tasks_done / $tasks * 100 );
			}
			$attr = array( 'progress' => $this->view_data['progress'] );
			$this->view_data['project']->update_attributes( $attr );
		}
		@$this->view_data['opentaskspercent'] = ( $tasks == 0 ? 0 : $tasks_done / $tasks * 100 );
		$projecthasworker = ProjectHasWorker::all( array(
			                                           'conditions' => array(
				                                           'user_id = ? AND project_id = ?',
				                                           $this->user->id,
				                                           $id
			                                           )
		                                           ) );
		@$this->view_data['worker_is_client_admin'] = CompanyHasAdmin::all( array(
			                                                                    'conditions' => array(
				                                                                    'user_id = ? AND
		 company_id = ?',
				                                                                    $this->user->id,
				                                                                    $this->view_data['project']->company_id
			                                                                    )
		                                                                    ) );
		if ( ! $projecthasworker && $this->user->admin != 1 && ! $this->view_data['worker_is_client_admin'] && false) {
			$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_no_access_error' ) );
			redirect( 'projects' );
		}
		$tracking = $this->view_data['project']->time_spent;
		if ( ! empty( $this->view_data['project']->tracking ) ) {
			$tracking = ( time() - $this->view_data['project']->tracking ) + $this->view_data['project']->time_spent;
		}
		$this->view_data['timertime']             = $tracking;
		$this->view_data['timertime']             = ProjectUserTimeTracking::getTotalTimeSpent( $id, $this->user->id );
		$this->view_data['is_tracking']           = ProjectUserTimeTracking::isTracking( $id, $this->user->id );
		$this->view_data['time_spent_from_today'] = time() - $this->view_data['project']->time_spent;
		$tracking                                 = floor( $tracking / 60 );
		$tracking_hours                           = floor( $tracking / 60 );
		$tracking_minutes                         = $tracking - ( $tracking_hours * 60 );


		$this->view_data['time_spent']         = $tracking_hours . " " . $this->lang->line( 'application_hours' ) . " " . $tracking_minutes . " " . $this->lang->line( 'application_minutes' );
		$this->view_data['time_spent_counter'] = sprintf( "%02s", $tracking_hours ) . ":" . sprintf( "%02s", $tracking_minutes );
		$this->view_data['comment_id']         = '-1';

		if ( $comment_guid && $taskId ) {
			$comment_guid = $taskId . '-' . $comment_guid;
			$mention      = Mention::find( 'all', array( 'conditions' => array( 'guid=?', $comment_guid ) ) );

			if ( isset( $mention[0] ) ) {
				$this->view_data['comment_id'] = $mention[0]->item_id;
			}
		}
		$this->view_data['my_id'] = $this->user->id;

		$this->content_view = 'projects/view';

	}

	function get( $id = false, $what = false )
	{
		$data = [];

		if ( $what ) {
			switch ( $what ) {
				case "workers":
					$workers = ProjectHasWorker::find('all', array('conditions' => array('project_id = ? AND user_id is not null', $id)));
					if(isset($workers)){
						$workers = array_map(function($worker) use (&$id) {
							$attributes = $worker->user->attributes();
							$attributes['userpic'] = get_user_pic($attributes['userpic']);
							$attributes['project_id'] = $id;
							return $attributes;
						}, $workers);
						$data = $workers;	
					}else{
						$data = array();
					}
					
					break;
				case "milestones":
					$milestones = ProjectHasMilestone::find('all', array(
						'conditions' => array('project_id = ?', $id),
						'order' => 'orderindex asc'
					));
					$milestones = array_map(function($milestone) {
						$tasks = ProjectHasTask::find('all', array(
							'conditions' => array('milestone_id = ?', $milestone->id),
							'order' => 'task_order asc'
						));
						$attributes = $milestone->attributes();

						$attributes['total'] = count($tasks);
						$attributes['completed'] = count(array_filter($tasks, function($task) {
							return $task->status == 'done';
						}));
						$attributes['tasks'] = array_map(function($task) {
							$attributes = $task->attributes();

							if ( $task->tracking != 0 && $task->tracking != "" ) {
								$attributes['timertime'] = ( time() - $task->tracking ) + $task->time_spent;
								$attributes['state'] = "resume";
							} else {
								$attributes['timertime'] = ( $task->time_spent != 0 && $task->time_spent != "" ) ? $task->time_spent : 0;
								$attributes['state'] = "pause";
							}

							$attributes['workers'] = array_map(function($worker) {
								$attributes = $worker->attributes();
								$attributes['worker'] = $worker->user->attributes();
								$attributes['worker']['userpic'] = get_user_pic($attributes['worker']['userpic']);
								return $attributes;
							}, $task->task_has_workers);

							$attributes['subtasks'] = array_map(function($subtask) {
								$attributes = $subtask->attributes();
								return $attributes;
							}, $task->task_has_subtasks);

							$attributes['comments'] = array_map(function($comment) {
								$attributes = $comment->attributes();
								$attributes['message'] = strip_tags($comment->message);
								$attributes['datetime'] = date("Y-m-d", $comment->datetime);

								$user_info = false;
								if ( $comment->user_id ) {
									$user_info = $comment->user->attributes();
								} else if ( $comment->client_id ) {
									$user_info = $comment->client->attributes();
								}

								$user_info['userpic'] = get_user_pic($user_info['userpic']);
								$attributes['user_info'] = $user_info;

								return $attributes;
							}, $task->task_has_comments);

							return $attributes;
						}, $tasks);

						return $attributes;
					}, $milestones);
					$data = $milestones;
					break;
				case 'tasks':
					$project_tasks = ProjectHasTask::find('all', array(
						'conditions' => array('project_id = ?', $id),
						'order' => 'task_order asc'
					));
					$tasks= [];
					foreach ($project_tasks as $task) {
						if ( $this->user->admin == 0 ) {
							$taskItem = TaskHasWorker::find( 'all', array(
								'conditions' => [
									'worker_id = ? AND task_id = ?',
									$this->user->id,
									$task->id,
								]
							) );
							if ( $taskItem ) {
								$tasks[] = $task;
							}
						} else {
							$tasks[] = $task;
						}
					}
					$data = [];
					foreach ($tasks as $task) $data[] = (array) $task;
					break;
				default:
					$project = Project::find( $id );
					$data    = $project->attributes();
					break;
			}
		}
		echo json_encode( [
			                  'status' => true,
			                  'data'   => $data
		                  ] );
		die();
	}

	function edit( $id = false, $what = false, $action = false )
	{
		$project = Project::find( $id );
		$status = false;
		$data = [];
		switch($what) {
			case "team":
				switch($action) {
					case "delete":
						if ( isset( $_GET['user_id'] ) ) {
							$user_id = $_GET['user_id'];
							$worker = ProjectHasWorker::find( 'all', array(
								'conditions' => array(
									'user_id = ? AND project_id = ?',
									$user_id,
									$id
								)
							) );

							if(!empty($worker[0]))
								$worker[0]->delete();

							echo json_encode( [
								                  'status' => true
							                  ] );
							die();
						}
						break;
				}
				break;
			case "tasks":
				$task = ProjectHasTask::find($id);
				$status = true;
				$data = (array) $task;
				break;
		}

		if($_POST) {
			switch($what) {
				case "milestones":
					switch($action) {
						case "create":
							$_POST['project_id'] = $id;
							$orderindex = ProjectHasMilestone::find_by_sql("SELECT MIN(orderindex) AS min_orderindex FROM project_has_milestones WHERE project_id = '{$id}'");
							$_POST['orderindex'] = $orderindex[0]->min_orderindex;

							if ( intval( $_POST['orderindex'] ) ) {
								$_POST['orderindex'] --;
							} else {
								$_POST['orderindex'] = -1;
							}
							$milestone = ProjectHasMilestone::create($_POST);

							echo json_encode( [
								                  'status' => true,
								                  'data'   => $milestone->attributes()
							                  ] );
							break;
						case "edit":
							if($_POST) {
								$milestone = ProjectHasMilestone::find($_POST['id']);
								unset($_POST['id']);
								$milestone->update_attributes($_POST);

								echo json_encode( [
									                  'status' => true
								                  ] );
							}
							break;
						case "delete":
							if($_POST) {
								$milestone = ProjectHasMilestone::find($_POST['id']);
								$milestone->delete();

								echo json_encode( [
									                  'status' => true
								                  ] );
							}
							break;
						case "order":
							if($_POST) {
								$index = - 1;
								$milestones = json_decode( $_POST['milestones'], true );

								foreach ( $milestones as $milestone ) {
									$index ++;
									$milestone = ProjectHasMilestone::find( $milestone );
									$milestone->update_attributes( [ 'orderindex' => $index ] );
								}
								echo json_encode( [
									                  'status' => true
								                  ] );
							}
							break;
					}
					break;
				case "tasks":
					switch ($action) {
						case "delete":
							$task = ProjectHasTask::find($id)->delete();
							$status = true;
							$data = [];
							break;
						case "update":
							$task = json_decode( $_POST['task'], true );
							$task = ProjectHasTask::find($id)->update_attributes($task);
							$status = true;
							$data = [];
							break;
						case "create":
							$task = json_decode( $_POST['task'], true );
							$task['project_id'] = $id;
							$task = ProjectHasTask::create($task);
							$status = true;
							$data = [];
							break;
					}
			}

			die();
		} else {
			echo json_encode( [
				'status' => $status,
				'data' => $data
			] );
			die();
		}

		$this->setTitle( $project->name );
		$this->content_view = "projects/project";
	}

	function tasks($id = false, $what = false, $action = false) {
		$data = [];

		if ( $what ) {
			switch ( $what ) {
				case "workers":
					if($action === 'add') {
						if(!empty($_POST['user_id'])) {

							$task = ProjectHasTask::find($id);
							$user_id = $_POST['user_id'];
							$created = false;
							$workers = TaskHasWorker::count( array(
								'conditions' => array(
									'task_id = ? AND worker_id = ?',
									$id,
									$user_id
								)
							) );

							if ( $workers <= 0 ) {
								$worker = TaskHasWorker::create( [
									                                  'task_id'   => $id,
									                                  'worker_id' => $user_id
								                                  ] );
								if(ProjectHasWorker::count(array('conditions' => array('project_id = ? AND user_id = ?',$task->project_id, $user_id))) <= 0) {
									ProjectHasWorker::create( [
										                          'project_id' => $task->project_id,
										                          'user_id'    => $user_id
									                          ] );
								}
								$worker_arr = $worker->attributes();
								$worker_arr['worker'] = $worker->user->attributes();
								$worker_arr['worker']['userpic'] = get_user_pic($worker_arr['worker']['userpic']);

								$created = true;

								echo json_encode( [
									                  'status' => $created,
									                  'worker' => $worker_arr
								                  ] );
							} else {
								echo json_encode( [
									                  'status' => $created
								                  ] );
							}
							die();
						}
					} else {
						$workers = TaskHasWorker::find( 'all', array( 'conditions' => array( 'task_id = ?', $id ) ) );
						$workers = array_map( function ( $worker ) {
							$attributes = $worker->user->attributes();
							$attributes['userpic'] = get_user_pic($attributes['userpic']);
							return $attributes;
						}, $workers );
						$data    = $workers;
					}
					break;
				case "update":
					if($_POST) {
						$task = ProjectHasTask::find( $id );
						$task->update_attributes( $_POST );

						echo json_encode( [
							                  'status' => true
						                  ] );
					}
					die();
					break;
				case 'check':
					$this->theme_view = 'blank';
					$task             = ProjectHasTask::find( $id );

					$total     = count( TaskHasSubtask::find_by_sql( "SELECT * from task_has_subtasks WHERE task_id = " . 1 ) );
					$completed = count( TaskHasSubtask::find_by_sql( "SELECT * from task_has_subtasks WHERE task_id = " . 1 . " AND status='done'" ) );

					if ( $completed < $total ) {
						json_response( "error", 'Sub tasks must be marked first' );
					} else {

						if ( $task->status == 'done' ) {
							$task->status = 'open';
						} else {
							$task->status = 'done';
						}
						if ( $task->tracking > 0 ) {
							json_response( "error", htmlspecialchars( $this->lang->line( 'application_task_timer_must_be_stopped_first' ) ) );
						}
						$task->save();
						$project    = Project::find( $task->project_id );
						$tasks      = ProjectHasTask::count( array( 'conditions' => 'project_id = ' . $task->project_id ) );
						$tasks_done = ProjectHasTask::count( array(
							                                     'conditions' => array(
								                                     'status = ? AND project_id = ?',
								                                     'done',
								                                     $task->project_id
							                                     )
						                                     ) );
						if ( $project->progress_calc == 1 ) {
							if ( $tasks ) {
								$progress = round( $tasks_done / $tasks * 100 );
							}
							$attr = array( 'progress' => $progress );
							$project->update_attributes( $attr );
						}
						if ( ! $task ) {
							json_response( "error", "Error while task toggle!" );
						}
						json_response( "success", "task_checked" );
					}
					break;
				case "create":
					if($_POST) {
						$_POST['user_id'] = $this->user->id;
						$task_order = ProjectHasTask::find_by_sql("SELECT MIN(task_order) AS min_task_order FROM project_has_tasks WHERE milestone_id = '{$id}'");
						$_POST['task_order'] = $task_order[0]->min_task_order;

						if ( intval( $_POST['task_order'] ) ) {
							$_POST['task_order'] --;
						} else {
							$_POST['task_order'] = -1;
						}
						$workers = !empty($_POST['workers']) ? $_POST['workers'] : "";

						unset($_POST['workers']);

						$task = ProjectHasTask::create($_POST);
						if(!empty($workers)) {
							$workers = explode( ",", $workers );

							foreach ( $workers as $worker ) {
								TaskHasWorker::create( [
									                       "task_id"   => $task->id,
									                       "worker_id" => $worker
								                       ] );
							}
						}

						echo json_encode( [
							                  'status' => true,
							                  'task'   => $task->attributes()
						                  ] );
					}
					die();
					break;
				case "get":
					$task = ProjectHasTask::find($id);
					$attributes = $task->attributes();
					if ( $task->tracking != 0 && $task->tracking != "" ) {
						$attributes['timertime'] = ( time() - $task->tracking ) + $task->time_spent;
						$attributes['state'] = "resume";
					} else {
						$attributes['timertime'] = ( $task->time_spent != 0 && $task->time_spent != "" ) ? $task->time_spent : 0;
						$attributes['state'] = "pause";
					}

					$attributes['workers'] = array_map(function($worker) {
						$attributes = $worker->attributes();
						$attributes['worker'] = $worker->user->attributes();
						$attributes['worker']['userpic'] = get_user_pic($attributes['worker']['userpic']);
						return $attributes;
					}, $task->task_has_workers);

					$attributes['subtasks'] = array_map(function($subtask) {
						$attributes = $subtask->attributes();
						return $attributes;
					}, $task->task_has_subtasks);

					$attributes['comments'] = array_map(function($comment) {
						$attributes = $comment->attributes();
						$attributes['message'] = strip_tags($comment->message);
						$attributes['datetime'] = date("Y-m-d", $comment->datetime);

						$user_info = false;
						if ( $comment->user_id ) {
							$user_info = $comment->user->attributes();
						} else if ( $comment->client_id ) {
							$user_info = $comment->client->attributes();
						}

						$user_info['userpic'] = get_user_pic($user_info['userpic']);
						$attributes['user_info'] = $user_info;

						return $attributes;
					}, $task->task_has_comments);

					$data = $attributes;
					break;
				case "subtasks":
					if ( $action === "add" ) {
						if ( ! empty( $_POST['name'] ) ) {
							$_POST['task_id'] = $id;
							$subtask          = TaskHasSubtask::create( $_POST );
							echo json_encode( [
								                  'status'  => true,
								                  'subtask' => $subtask->attributes()
							                  ] );
						}
						die();
					}
					break;
				case "comments":
					if($action === "add") {

						if(!empty($this->user->id))
							$_POST['user_id'] = $this->user->id;

						if(!empty($this->client->id))
							$_POST['client_id'] = $this->client->id;

						$_POST['datetime'] = time();
						$_POST['task_id'] = $id;

						$comment = TaskHasComment::create($_POST);
						$comment_arr = $comment->attributes();

						$comment_arr['message'] = strip_tags($comment->message);
						$comment_arr['datetime'] = date("Y-m-d", $comment->datetime);

						$user_info = false;
						if ( $comment->user_id ) {
							$user_info = $comment->user->attributes();
						} else if ( $comment->client_id ) {
							$user_info = $comment->client->attributes();
						}

						$user_info['userpic'] = get_user_pic($user_info['userpic']);
						$comment_arr['user_info'] = $user_info;

						echo json_encode( [
							                  'status'  => true,
							                  'comment' => $comment_arr
						                  ] );
						die();
					}
					break;
				case "order":
					if($_POST) {
						$index = - 1;
						$tasks = json_decode( $_POST['tasks'], true );

						foreach ( $tasks as $task ) {
							$index ++;
							$task = ProjectHasTask::find( $task );
							$task->update_attributes( [ 'task_order' => $index ] );
						}
						echo json_encode( [
							                  'status' => true
						                  ] );
						die();
					}
					break;
			}
		}

		echo json_encode( [
			                  'status' => true,
			                  'data'   => $data
		                  ] );
		die();
	}

	function subtasks($id = false, $action = false) {
		$subtask = TaskHasSubtask::find($id);

		switch($action) {
			case "edit":
				if($_POST) {
					$subtask->update_attributes( $_POST );
					echo json_encode( [
						                  'status' => true
					                  ] );
				}
				break;
		}
		die();
	}

	function ganttChart( $id )
	{
		$gantt_data = "[";
		$project    = Project::find_by_id( $id );
		foreach ( $project->project_has_milestones as $milestone ):

			$counter = 0;
			foreach ( $milestone->project_has_tasks as $value ):
				$milestone_Name = ( $counter == 0 ) ? $milestone->name : "";
				$counter ++;
				$start = ( $value->start_date ) ? $value->start_date : $milestone->start_date;
				$end   = ( $value->due_date ) ? $value->due_date : $milestone->due_date;

				$gantt_data .= '{ name: "' . $milestone_Name . '", desc: "' . $value->name . '", values: [';

				$gantt_data .= '{ label: "' . $value->name . '", from: "' . $start . '", to: "' . $end . '" }';
				$gantt_data .= ']},';
			endforeach;

		endforeach;
		$gantt_data       .= "]";
		$this->theme_view = 'blank';


		echo $gantt_data;


	}

	function quicktask()
	{
		if ( $_POST ) {
			$workers = [];
			$this->load->helper( 'mention' );

			$project = Project::find( $_POST['project_id'] );

			$_POST['name'] = strip_tags( $_POST['name'] );
			$_POST         = array_map( 'htmlspecialchars', $_POST );

			unset( $_POST['send'] );
			unset( $_POST['files'] );

			if ( $mention_users = has_mentioned( $_POST['name'] ) ) {
				foreach ( $mention_users as $worker ) {
					$_POST['name'] = str_replace( '@' . $worker->username, '', $_POST['name'] );
					$workers[]     = $worker->id;
				}
			}

			$task = ProjectHasTask::create( $_POST );

			foreach ( $workers as $worker ) {
				$is_project_worker = ProjectHasWorker::find( 'all', array(
					'conditions' => array(
						'user_id = ?',
						$worker
					)
				) );

				if ( ! $is_project_worker ) {
					ProjectHasWorker::create( array(
						                          'project_id' => $project->id,
						                          'user_id'    => $worker
					                          ) );
				}

				TaskHasWorker::create( array(
					                       'worker_id' => $worker,
					                       'task_id'   => $task->id
				                       ) );
			}

			echo $task->id;
		}

		$this->theme_view = 'blank';
	}

	function quicksubtask()
	{
		if ( $_POST ) {
			$_POST   = array_map( 'htmlspecialchars', $_POST );
			$subtask = TaskHasSubtask::create( $_POST );
			echo $subtask->id;
		}

		$this->theme_view = 'blank';
	}

	function generate_thumbs( $id = false )
	{
		if ( $id ) {
			$medias = Project::find_by_id( $id )->project_has_files;
			//check image processor extension
			if ( extension_loaded( 'gd2' ) ) {
				$lib = 'gd2';
			} else {
				$lib = 'gd';
			}
			foreach ( $medias as $value ) {
				if ( ! file_exists( './files/media/thumb_' . $value->savename ) ) {

					$config['image_library']  = $lib;
					$config['source_image']   = './files/media/' . $value->savename;
					$config['new_image']      = './files/media/thumb_' . $value->savename;
					$config['create_thumb']   = true;
					$config['thumb_marker']   = "";
					$config['maintain_ratio'] = true;
					$config['width']          = 170;
					$config['height']         = 170;
					$config['master_dim']     = "height";
					$config['quality']        = "100%";
					$this->load->library( 'image_lib' );
					$this->image_lib->initialize( $config );
					$this->image_lib->resize();
					$this->image_lib->clear();

				}
			}
			redirect( 'projects/view/' . $id );
		}
	}

	function dropzone( $id = false )
	{

		$attr                    = array();
		$config['upload_path']   = './files/media/';
		$config['encrypt_name']  = true;
		$config['allowed_types'] = '*';

		$this->load->library( 'upload', $config );


		if ( $this->upload->do_upload( "file" ) ) {
			$data = array( 'upload_data' => $this->upload->data() );

			$attr['name']     = $data['upload_data']['orig_name'];
			$attr['filename'] = $data['upload_data']['orig_name'];
			$attr['savename'] = $data['upload_data']['file_name'];
			$attr['type']     = $data['upload_data']['file_type'];
			$attr['date']     = date( "Y-m-d H:i", time() );
			$attr['phase']    = "";

			$attr['project_id'] = $id;
			$attr['user_id']    = $this->user->id;
			$media              = ProjectHasFile::create( $attr );
			echo $media->id;

			//check image processor extension
			if ( extension_loaded( 'gd2' ) ) {
				$lib = 'gd2';
			} else {
				$lib = 'gd';
			}
			$config['image_library']  = $lib;
			$config['source_image']   = './files/media/' . $attr['savename'];
			$config['new_image']      = './files/media/thumb_' . $attr['savename'];
			$config['create_thumb']   = true;
			$config['thumb_marker']   = "";
			$config['maintain_ratio'] = true;
			$config['width']          = 170;
			$config['height']         = 170;
			$config['master_dim']     = "height";
			$config['quality']        = "100%";


			$this->load->library( 'image_lib' );
			$this->image_lib->initialize( $config );
			$this->image_lib->resize();
			$this->image_lib->clear();
		} else {
			echo "Upload faild";
			$error = $this->upload->display_errors( '', ' ' );
			$this->session->set_flashdata( 'message', 'error:' . $error );
			echo $error;

		}


		$this->theme_view = 'blank';
	}

	function timesheets( $taskid )
	{

		$this->view_data['timesheets'] = ProjectHasTimesheet::find( "all", array(
			"conditions" => array(
				"task_id = ?",
				$taskid
			)
		) );
		$this->view_data['task']       = ProjectHasTask::find_by_id( $taskid );

		$this->theme_view               = 'modal';
		$this->view_data['title']       = $this->lang->line( 'application_timesheet' );
		$this->view_data['form_action'] = 'projects/timesheet_add';
		$this->content_view             = 'projects/_timesheets';
	}

	function timesheet_add()
	{
		if ( $_POST ) {
			$time             = ( $_POST["hours"] * 3600 ) + ( $_POST["minutes"] * 60 );
			$attr             = array(
				"project_id"  => $_POST["project_id"],
				"user_id"     => $_POST["user_id"],
				"time"        => $time,
				"client_id"   => 0,
				"task_id"     => $_POST["task_id"],
				"start"       => $_POST["start"],
				"end"         => $_POST["end"],
				"invoice_id"  => 0,
				"description" => "",
			);
			$timesheet        = ProjectHasTimesheet::create( $attr );
			$task             = ProjectHasTask::find_by_id( $timesheet->task_id );
			$task->time_spent = $task->time_spent + $time;
			$task->save();
			echo $timesheet->id;
		}
		$this->theme_view = 'blank';
	}

	function timesheet_delete( $timesheet_id )
	{

		$timesheet        = ProjectHasTimesheet::find_by_id( $timesheet_id );
		$task             = ProjectHasTask::find_by_id( $timesheet->task_id );
		$task->time_spent = $task->time_spent - $timesheet->time;
		$task->save();
		$timesheet->delete();
		$this->theme_view = 'blank';
	}

	function task( $id = false, $condition = false, $task_id = false )
	{
		$this->view_data['submenu'] = array(
			$this->lang->line( 'application_back' )     => 'projects',
			$this->lang->line( 'application_overview' ) => 'projects/view/' . $id,
		);
		switch ( $condition ) {
			case 'add':
				$this->content_view = 'projects/_tasks';
				if ( $_POST ) {
					$user_ids = $_POST['user_ids'];

					unset( $_POST['send'] );
					unset( $_POST['files'] );
					unset( $_POST['user_ids'] );

					$description          = $_POST['description'];
					$_POST                = array_map( 'htmlspecialchars', $_POST );
					$_POST['description'] = $description;
					$_POST['project_id']  = $id;
					$_POST['user_id']     = $user_ids[0];
					$_POST['value']       = str_replace( ',', '', $_POST['value'] );
					$task                 = ProjectHasTask::create( $_POST );
					if ( ! $task ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_task_error' ) );
					} else {
						foreach ( $user_ids as $worker_id ) {
							TaskHasWorker::create( array(
								                       'worker_id' => $worker_id,
								                       'task_id'   => $task->id
							                       ) );
						}

						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_task_success' ) );
					}
					redirect( 'projects/view/' . $id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['project']     = Project::find( $id );
					$this->view_data['title']       = $this->lang->line( 'application_add_task' );
					$this->view_data['form_action'] = 'projects/tasks/' . $id . '/add';
					$this->content_view             = 'projects/_tasks';
				}
				break;
			case 'update':
				$this->content_view      = 'projects/_tasks';
				$this->view_data['task'] = ProjectHasTask::find( $task_id );
				if ( $_POST ) {
					$user_ids = $_POST['user_ids'];

					unset( $_POST['send'] );
					unset( $_POST['files'] );
					unset( $_POST['user_ids'] );

					$_POST['user_id'] = $user_ids[0];
					$_POST['value']   = str_replace( ',', '', $_POST['value'] );
					if ( ! isset( $_POST['public'] ) ) {
						$_POST['public'] = 0;
					}
					$description          = $_POST['description'];
					$_POST                = array_map( 'htmlspecialchars', $_POST );
					$_POST['description'] = $description;
					$task_id              = $_POST['id'];
					$task                 = ProjectHasTask::find( $task_id );

					if ( $task->user_id != $_POST['user_id'] ) {
						//stop timer and add time to timesheet
						if ( $task->tracking != 0 ) {
							$now              = time();
							$diff             = $now - $task->tracking;
							$timer_start      = $task->tracking;
							$task->time_spent = $task->time_spent + $diff;
							$task->tracking   = "";
							$attributes       = array(
								'task_id'    => $task->id,
								'user_id'    => $task->user_id,
								'project_id' => $task->project_id,
								'client_id'  => 0,
								'time'       => $diff,
								'start'      => $timer_start,
								'end'        => $now
							);
							$timesheet        = ProjectHasTimesheet::create( $attributes );
						}
					}

					$assigned_users = array_map( function ( $worker ) {
						return $worker->user->id;
					}, $task->task_has_workers );

					$old_assigned_users = array_filter( $assigned_users, function ( $worker_id ) use ( &$user_ids ) {
						return ! in_array( $worker_id, $user_ids );
					} );

					foreach ( $user_ids as $worker_id ) {
						if ( ! in_array( $worker_id, $assigned_users ) ) {
							TaskHasWorker::create( array(
								                       'worker_id' => $worker_id,
								                       'task_id'   => $task->id
							                       ) );
						}
					}

					TaskHasWorker::delete_all( array(
						                           'conditions' => array(
							                           'worker_id in(?) AND task_id=?',
							                           implode( ',', $old_assigned_users ),
							                           $task->id
						                           )
					                           ) );

					$task->update_attributes( $_POST );
					if ( ! $task ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_task_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_task_success' ) );
					}
					redirect( 'projects/view/' . $id . '/tasks' );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['project']     = Project::find( $id );
					$this->view_data['title']       = $this->lang->line( 'application_edit_task' );
					$this->view_data['form_action'] = 'projects/task/' . $id . '/update/' . $task_id;
					$this->content_view             = 'projects/_tasks';
				}
				break;
			case 'check':
				$this->theme_view = 'blank';
				$task             = ProjectHasTask::find( $task_id );

				$total     = count( TaskHasSubtask::find_by_sql( "SELECT * from task_has_subtasks WHERE task_id = " . 1 ) );
				$completed = count( TaskHasSubtask::find_by_sql( "SELECT * from task_has_subtasks WHERE task_id = " . 1 . " AND status='done'" ) );

				if ( $completed < $total ) {
					json_response( "error", 'Sub tasks must be marked first' );
				} else {

					if ( $task->status == 'done' ) {
						$task->status = 'open';
					} else {
						$task->status = 'done';
					}
					if ( $task->tracking > 0 ) {
						json_response( "error", htmlspecialchars( $this->lang->line( 'application_task_timer_must_be_stopped_first' ) ) );
					}
					$task->save();
					$project    = Project::find( $id );
					$tasks      = ProjectHasTask::count( array( 'conditions' => 'project_id = ' . $id ) );
					$tasks_done = ProjectHasTask::count( array(
						                                     'conditions' => array(
							                                     'status = ? AND project_id = ?',
							                                     'done',
							                                     $id
						                                     )
					                                     ) );
					if ( $project->progress_calc == 1 ) {
						if ( $tasks ) {
							$progress = round( $tasks_done / $tasks * 100 );
						}
						$attr = array( 'progress' => $progress );
						$project->update_attributes( $attr );
					}
					if ( ! $task ) {
						json_response( "error", "Error while task toggle!" );
					}
					json_response( "success", "task_checked" );
				}
				break;
			case 'unlock':
				$this->theme_view = 'blank';
				$task             = ProjectHasTask::find( $task_id );
				$task->invoice_id = '0';
				$task->save();
				if ( $task ) {
					json_response( "success", htmlspecialchars( $this->lang->line( 'application_task_has_been_unlocked' ) ) );
				} else {
					json_response( "error", htmlspecialchars( $this->lang->line( 'application_task_has_not_been_unlocked' ) ) );
				}
				break;
			case 'delete':
				$task = ProjectHasTask::find( $task_id );
				$task->delete();
				if ( ! $task ) {
					$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_task_error' ) );
				} else {
					$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_task_success' ) );
				}
				redirect( 'projects/view/' . $id . '/tasks' );
				break;
			default:
				$this->view_data['project'] = Project::find( $id );
				$this->content_view         = 'projects/tasks';
				break;
		}

	}

	function subtasks1( $project_id = false, $task_id = false, $condition = false, $subtask_id = false )
	{
		$this->view_data['submenu'] = array(
			$this->lang->line( 'application_back' )     => 'projects',
			$this->lang->line( 'application_overview' ) => 'projects/view/' . $project_id,
		);
		switch ( $condition ) {
			case 'update':
				$this->content_view         = 'projects/_subtasks';
				$this->view_data['subtask'] = TaskHasSubtask::find( $subtask_id );
				if ( $_POST ) {

					$subtask = TaskHasSubtask::find( $subtask_id );

					$subtask->update_attributes( $_POST );
					if ( ! $subtask ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_task_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_task_success' ) );
					}
					redirect( 'projects/view/' . $project_id . '/' . $task_id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['project']     = Project::find( $project_id );
					$this->view_data['title']       = $this->lang->line( 'application_edit_task' );
					$this->view_data['form_action'] = 'projects/subtasks/' . $project_id . '/' . $task_id . '/update/' . $subtask_id;
					$this->content_view             = 'projects/_subtasks';
				}
				break;
			case 'check':
				$this->theme_view = 'blank';
				$subtask          = TaskHasSubtask::find( $subtask_id );
				if ( $subtask->status == 'done' ) {
					$subtask->status = 'open';
				} else {
					$subtask->status = 'done';
				}
				$subtask->save();

				if ( ! $subtask ) {
					json_response( "error", "Error while subtask toggle!" );
				}
				json_response( "success", "task_checked" );
				break;
			case 'delete':
				$subtask = TaskHasSubtask::find( $subtask_id );
				$subtask->delete();
				if ( ! $subtask ) {
					$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_task_error' ) );
				} else {
					$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_task_success' ) );
				}
				redirect( 'projects/view/' . $project_id . '/' . $task_id );
				break;
			default:
				$this->view_data['project'] = Project::find( $project_id );
				$this->content_view         = 'projects/tasks';
				break;
		}

	}

	function milestones1( $id = false, $condition = false, $milestone_id = false )
	{
		$this->view_data['submenu'] = array(
			$this->lang->line( 'application_back' )     => 'projects',
			$this->lang->line( 'application_overview' ) => 'projects/view/' . $id,
		);
		switch ( $condition ) {
			case 'add':
				$this->content_view = 'projects/_milestones';
				if ( $_POST ) {
					unset( $_POST['send'] );
					unset( $_POST['files'] );
					$description          = $_POST['description'];
					$_POST                = array_map( 'htmlspecialchars', $_POST );
					$_POST['description'] = $description;
					$_POST['project_id']  = $id;
					$milestone            = ProjectHasMilestone::create( $_POST );
					if ( ! $milestone ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_milestone_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_milestone_success' ) );
					}
					redirect( 'projects/view/' . $id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['project']     = Project::find( $id );
					$this->view_data['title']       = $this->lang->line( 'application_add_milestone' );
					$this->view_data['form_action'] = 'projects/milestones/' . $id . '/add';
					$this->content_view             = 'projects/_milestones';
				}
				break;
			case 'update':
				$this->content_view           = 'projects/_milestones';
				$this->view_data['milestone'] = ProjectHasMilestone::find( $milestone_id );
				if ( $_POST ) {
					unset( $_POST['send'] );
					unset( $_POST['files'] );
					$description          = $_POST['description'];
					$_POST                = array_map( 'htmlspecialchars', $_POST );
					$_POST['description'] = $description;
					$milestone_id         = $_POST['id'];
					$milestone            = ProjectHasMilestone::find( $milestone_id );
					$milestone->update_attributes( $_POST );
					if ( ! $milestone ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_milestone_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_milestone_success' ) );
					}
					redirect( 'projects/view/' . $id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['project']     = Project::find( $id );
					$this->view_data['title']       = $this->lang->line( 'application_edit_milestone' );
					$this->view_data['form_action'] = 'projects/milestones/' . $id . '/update/' . $milestone_id;
					$this->content_view             = 'projects/_milestones';
				}
				break;
			case 'delete':
				$milestone = ProjectHasMilestone::find( $milestone_id );

				foreach ( $milestone->project_has_tasks as $value ) {
					$value->milestone_id = "";
					$value->save();
				}
				$milestone->delete();
				if ( ! $milestone ) {
					$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_milestone_error' ) );
				} else {
					$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_milestone_success' ) );
				}
				redirect( 'projects/view/' . $id );
				break;
			default:
				$this->view_data['project'] = Project::find( $id );
				$this->content_view         = 'projects/milestones';
				break;
		}

	}

	function notes( $id = false )
	{
		if ( $_POST ) {
			unset( $_POST['send'] );
			$_POST         = array_map( 'htmlspecialchars', $_POST );
			$_POST['note'] = strip_tags( $_POST['note'] );
			$project       = Project::find( $id );
			$project->update_attributes( $_POST );
		}
		$this->theme_view = 'ajax';
	}

	function media( $id = false, $condition = false, $media_id = false )
	{
		$this->load->helper( 'notification' );
		$this->view_data['submenu'] = array(
			$this->lang->line( 'application_back' )     => 'projects',
			$this->lang->line( 'application_overview' ) => 'projects/view/' . $id,
			$this->lang->line( 'application_tasks' )    => 'projects/tasks/' . $id,
			$this->lang->line( 'application_media' )    => 'projects/media/' . $id,
		);
		switch ( $condition ) {
			case 'view':

				if ( $_POST ) {
					unset( $_POST['send'] );
					unset( $_POST['_wysihtml5_mode'] );
					unset( $_POST['files'] );
					//$_POST = array_map('htmlspecialchars', $_POST);
					$_POST['text'] = $_POST['message'];
					unset( $_POST['message'] );
					$_POST['project_id']        = $id;
					$_POST['media_id']          = $media_id;
					$_POST['from']              = $this->user->firstname . ' ' . $this->user->lastname;
					$this->view_data['project'] = Project::find_by_id( $id );
					$this->view_data['media']   = ProjectHasFile::find( $media_id );
					$message                    = Message::create( $_POST );
					if ( ! $message ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_message_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_message_success' ) );

						foreach ( $this->view_data['project']->project_has_workers as $workers ) {
							send_user_notification(
								$this->user,
								$workers->user->email,
								"[" . $this->view_data['project']->name . "] New comment",
								'New comment on media file: ' . $this->view_data['media']->name . '<br><strong>' . $this->view_data['project']->name . '</strong>',
								false, base_ur() . 'projects/view/' . $id . '#media-tab' );
						}
						if ( isset( $this->view_data['project']->company->client->email ) ) {
							$access = explode( ',', $this->view_data['project']->company->client->access );
							if ( in_array( '12', $access ) ) {
								send_user_notification(
									$this->user,
									$this->view_data['project']->company->client->email,
									"[" . $this->view_data['project']->name . "] New comment",
									'New comment on media file: ' . $this->view_data['media']->name . '<br><strong>' . $this->view_data['project']->name . '</strong>',
									false,
									base_url() . 'projects/view/' . $id . '#media-tab'
								);
							}
						}
					}
					redirect( 'projects/media/' . $id . '/view/' . $media_id );
				}
				$this->content_view             = 'projects/view_media';
				$this->view_data['media']       = ProjectHasFile::find( $media_id );
				$this->view_data['form_action'] = 'projects/media/' . $id . '/view/' . $media_id;
				$this->view_data['filetype']    = explode( '.', $this->view_data['media']->filename );
				$this->view_data['filetype']    = $this->view_data['filetype'][1];
				$this->view_data['backlink']    = 'projects/view/' . $id;
				break;
			case 'add':
				$this->content_view         = 'projects/_media';
				$this->view_data['project'] = Project::find( $id );
				if ( $_POST ) {
					$config['upload_path']   = './files/media/';
					$config['encrypt_name']  = true;
					$config['allowed_types'] = '*';

					$this->load->library( 'upload', $config );

					if ( ! $this->upload->do_upload() ) {
						$error = $this->upload->display_errors( '', ' ' );
						$this->session->set_flashdata( 'message', 'error:' . $error );
						redirect( 'projects/media/' . $id );
					} else {
						$data = array( 'upload_data' => $this->upload->data() );

						$_POST['filename'] = $data['upload_data']['orig_name'];
						$_POST['savename'] = $data['upload_data']['file_name'];
						$_POST['type']     = $data['upload_data']['file_type'];

						//check image processor extension
						if ( extension_loaded( 'gd2' ) ) {
							$lib = 'gd2';
						} else {
							$lib = 'gd';
						}
						$config['image_library']  = $lib;
						$config['source_image']   = './files/media/' . $_POST['savename'];
						$config['new_image']      = './files/media/thumb_' . $_POST['savename'];
						$config['create_thumb']   = true;
						$config['thumb_marker']   = "";
						$config['maintain_ratio'] = true;
						$config['width']          = 170;
						$config['height']         = 170;
						$config['master_dim']     = "height";
						$config['quality']        = "100%";

						$this->load->library( 'image_lib' );
						$this->image_lib->initialize( $config );
						$this->image_lib->resize();
						$this->image_lib->clear();
					}

					unset( $_POST['send'] );
					unset( $_POST['userfile'] );
					unset( $_POST['file-name'] );
					unset( $_POST['files'] );
					$_POST               = array_map( 'htmlspecialchars', $_POST );
					$_POST['project_id'] = $id;
					$_POST['user_id']    = $this->user->id;
					$media               = ProjectHasFile::create( $_POST );
					if ( ! $media ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_media_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_media_success' ) );

						$attributes = array(
							'subject'    => $this->lang->line( 'application_new_media_subject' ),
							'message'    => '<b>' . $this->user->firstname . ' ' . $this->user->lastname . '</b> ' . $this->lang->line( 'application_uploaded' ) . ' ' . $_POST['name'],
							'datetime'   => time(),
							'project_id' => $id,
							'type'       => 'media',
							'user_id'    => $this->user->id
						);
						$activity   = ProjectHasActivity::create( $attributes );

						foreach ( $this->view_data['project']->project_has_workers as $workers ) {
							send_user_notification(
								$this->user,
								$workers->user->email,
								"[" . $this->view_data['project']->name . "] " . $this->lang->line( 'application_new_media_subject' ),
								$this->lang->line( 'application_new_media_file_was_added' ) . ' <strong>' . $this->view_data['project']->name . '</strong>',
								false,
								base_url() . 'projects/view/' . $id . '#media-tab'
							);
						}
						if ( isset( $this->view_data['project']->company->client->email ) ) {
							$access = explode( ',', $this->view_data['project']->company->client->access );
							if ( in_array( '12', $access ) ) {
								send_user_notification(
									$this->user,
									$this->view_data['project']->company->client->email,
									"[" . $this->view_data['project']->name . "] " . $this->lang->line( 'application_new_media_subject' ),
									$this->lang->line( 'application_new_media_file_was_added' ) . ' <strong>' . $this->view_data['project']->name . '</strong>',
									false,
									base_url() . 'projects/view/' . $id . '#media-tab'
								);
							}
						}

					}
					redirect( 'projects/view/' . $id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['title']       = $this->lang->line( 'application_add_media' );
					$this->view_data['form_action'] = 'projects/media/' . $id . '/add';
					$this->content_view             = 'projects/_media';
				}
				break;
			case 'update':
				$this->content_view         = 'projects/_media';
				$this->view_data['media']   = ProjectHasFile::find( $media_id );
				$this->view_data['project'] = Project::find( $id );
				if ( $_POST ) {
					unset( $_POST['send'] );
					unset( $_POST['_wysihtml5_mode'] );
					unset( $_POST['files'] );
					$_POST    = array_map( 'htmlspecialchars', $_POST );
					$media_id = $_POST['id'];
					$media    = ProjectHasFile::find( $media_id );
					$media->update_attributes( $_POST );
					if ( ! $media ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_media_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_media_success' ) );
					}
					redirect( 'projects/view/' . $id );
				} else {
					$this->theme_view               = 'modal';
					$this->view_data['title']       = $this->lang->line( 'application_edit_media' );
					$this->view_data['form_action'] = 'projects/media/' . $id . '/update/' . $media_id;
					$this->content_view             = 'projects/_media';
				}
				break;
			case 'delete':
				$media = ProjectHasFile::find( $media_id );
				$media->delete();
				$this->load->database();
				$sql = "DELETE FROM messages WHERE media_id = $media_id";
				$this->db->query( $sql );
				if ( ! $media ) {
					$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_media_error' ) );
				} else {
					unlink( './files/media/' . $media->savename );
					$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_media_success' ) );
				}
				redirect( 'projects/view/' . $id );
				break;
			default:
				$this->view_data['project'] = Project::find( $id );
				$this->content_view         = 'projects/view/' . $id;
				break;
		}

	}

	function deletemessage( $project_id, $media_id, $id )
	{
		$message = Message::find( $id );
		if ( $message->from == $this->user->firstname . " " . $this->user->lastname || $this->user->admin == "1" ) {
			$message->delete();
		}
		if ( ! $message ) {
			$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_delete_message_error' ) );
		} else {
			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_delete_message_success' ) );
		}
		redirect( 'projects/media/' . $project_id . '/view/' . $media_id );
	}

	function tracking( $id = false )
	{
		$project  = Project::find( $id );
		$tracking = ProjectUserTimeTracking::find( "all", array(
			"conditions" => array(
				"user_id = ? and project_id = ? and time_spent = 0",
				$this->user->id,
				$id
			)
		) );

		if ( empty( $tracking ) ) {
			//$project->update_attributes(array('tracking' => time()));
			ProjectUserTimeTracking::create( array(
				                                 'project_id' => $id,
				                                 'user_id'    => $this->user->id,
				                                 'time_start' => time(),
				                                 'time_spent' => 0
			                                 ) );
		} else {
			$tracking = $tracking[0];
			$time_end = time();
			$timeDiff = $time_end - $tracking->time_start;
			$tracking->update_attributes( array(
				                              'time_end'   => $time_end,
				                              'time_spent' => $tracking->time_spent + $timeDiff
			                              ) );
		}
		redirect( 'projects/view/' . $id . '/tasks' );

	}

	function sticky( $id = false )
	{
		$project = Project::find( $id );
		if ( $project->sticky == 0 ) {
			$project->update_attributes( array( 'sticky' => '1' ) );
			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_make_sticky_success' ) );

		} else {
			$project->update_attributes( array( 'sticky' => '0' ) );
			$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_remove_sticky_success' ) );
		}
		redirect( 'projects/view/' . $id );

	}

	function sticky_json( $id = false )
	{
		$project = Project::find( $id );
		if ( $project->sticky == 0 ) {
			$project->update_attributes( array( 'sticky' => '1' ) );
		} else {
			$project->update_attributes( array( 'sticky' => '0' ) );
		}
		echo json_encode( [
			'status' => ($project->sticky == 1)
		] );
		die();

	}


	function download( $media_id = false, $comment_file = false )
	{

		$this->load->helper( 'download' );
		$this->load->helper( 'file' );
		if ( $media_id && $media_id != "false" ) {
			$media                   = ProjectHasFile::find( $media_id );
			$media->download_counter = $media->download_counter + 1;
			$media->save();
			$file = './files/media/' . $media->savename;
		}
		if ( $comment_file && $comment_file != "false" ) {
			$file = './files/media/' . $comment_file;
		}

		$mime = get_mime_by_extension( $file );
		if ( file_exists( $file ) ) {
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: ' . $mime );
			header( 'Content-Disposition: attachment; filename=' . basename( $media->filename ) );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file ) );
			readfile( $file );
			@ob_clean();
			@flush();
			exit;
		}
	}

	function task_comment( $id, $condition )
	{
		$this->load->helper( 'notification' );
		$task    = ProjectHasTask::find( $id );
		$project = $task->project;

		switch ( $condition ) {
			case 'create':
				if ( $_POST ) {

					$config['upload_path']   = './files/media/';
					$config['encrypt_name']  = true;
					$config['allowed_types'] = '*';
					$this->load->library( 'upload', $config );
					$this->load->helper( 'mention' );

					unset( $_POST['send'] );
					//$_POST['message'] = htmlspecialchars(strip_tags($_POST['message'], '<br><br/><p></p><a></a><b></b><i></i><u></u><span></span>'));
					$_POST['task_id']  = $id;
					$_POST['user_id']  = $this->user->id;
					$_POST['datetime'] = time();

					$attachment = false;
					if ( ! $this->upload->do_upload() ) {
						$error = $this->upload->display_errors( '', ' ' );
						if ( $error != 'You did not select a file to upload.' ) {
							//$this->session->set_flashdata('message', 'error:'.$error);
						}
					} else {
						$data                     = array( 'upload_data' => $this->upload->data() );
						$_POST['attachment']      = $data['upload_data']['orig_name'];
						$_POST['attachment_link'] = $data['upload_data']['file_name'];
						$attachment               = $data['upload_data']['file_name'];
					}
					unset( $_POST['userfile'] );

					$comment = TaskHasComment::create( $_POST );
					if ( ! $comment ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_success' ) );

						if ( $mention_users = has_mentioned( $_POST['message'] ) ) {
							$mention_guid   = generate_mention_guid( $task->id );
							$mention_action = "/projects/view/{$project->id}/{$task->id}/{$mention_guid}";

							foreach ( $mention_users as $mention_user ) {
								$mention = Mention::create( [
									                            'guid'    => $task->id . '-' . $mention_guid,
									                            'mention' => $mention_user->username,
									                            'comment' => $_POST['message'],
									                            'action'  => $mention_action,
									                            'user_id' => $this->user->id,
									                            'item_id' => $task->id . $comment->id,
									                            'is_read' => false
								                            ] );

								if ( $mention->guid ) {
									$mention_action = base_url( $mention_action );

									$mention_email_body = $_POST['message'];
									$mention_email_body .= "<a href='{$mention_action}'>{$this->lang->line('application_view_task')}</a>";

									send_notification( $mention_user->email, 'Mention', $mention_email_body );
								}
							}
						}

						if ( $this->user->id != $task->user->id ) {
							send_user_notification(
								$this->user,
								$task->user->email,
								$project->name . " | New task message",
								$_POST['message'] . '<br><strong>' . $project->name . '</strong>',
								false, base_url() . '/projects/view/' . $project->id .
								       '?task_id=' . $id . '#tasks-tab' );
						}
						// if(isset($project->company->client->email)){
						// 	$access = explode(',', $project->company->client->access);
						// 	if(in_array('12', $access)){
						// 		send_notification($project->company->client->email, "[".$project->name."] ".$_POST['subject'], $_POST['message'].'<br><strong>'.$project->name.'</strong>');
						// 	}
						// }
					}
					echo "success";
					exit;

				}
				break;
		}
	}

	function invoice( $id = false )
	{
		if ( $_POST ) {

			unset( $_POST['send'] );
			unset( $_POST['_wysihtml5_mode'] );
			unset( $_POST['files'] );
			$project               = Project::find_by_id( $id );
			$values                = array(
				"project_id" => $id,
				"company_id" => $project->company_id,
				"status"     => "Open",
				"reference"  => $_POST["reference"],
				"issue_date" => $_POST["issue_date"],
				"due_date"   => $_POST["due_date"],
				"terms"      => $_POST["terms"],
				"currency"   => $_POST["currency"],
				"discount"   => $_POST["discount"],
				"tax"        => $_POST["tax"],
				"second_tax" => $_POST["second_tax"]
			);
			$invoice               = Invoice::create( $values );
			$new_invoice_reference = $_POST['reference'] + 1;
			if ( is_array( $_POST["tasks"] ) ) {
				foreach ( $_POST["tasks"] as $value ) {
					$task             = ProjectHasTask::find_by_id( $value );
					$task->invoice_id = $invoice->id;
					$task->save();
					$seconds     = $task->time_spent;
					$H           = floor( $seconds / 3600 );
					$i           = ( $seconds / 60 ) % 60;
					$s           = $seconds % 60;
					$hours       = sprintf( '%0.2f', $H + ( $i / 60 ) );
					$item_values = array(
						"invoice_id"  => $invoice->id,
						"item_id"     => 0,
						"amount"      => $hours,
						"value"       => $task->value,
						"name"        => $task->name,
						"description" => $task->description,
						"type"        => "task",
						"task_id"     => $task->id
					);
					$newItem     = InvoiceHasItem::create( $item_values );
				}
			}


			$invoice_reference = Setting::first();
			$invoice_reference->update_attributes( array( 'invoice_reference' => $new_invoice_reference ) );
			if ( ! $invoice ) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_create_invoice_error' ) );
			} else {
				$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_create_invoice_success' ) );
			}
			redirect( 'invoices/view/' . $invoice->id );
		} else {
			$this->view_data['invoices']       = Invoice::all();
			$this->view_data['next_reference'] = Invoice::last();
			$this->view_data['project']        = Project::find_by_id( $id );
			$this->view_data['done_tasks']     = ProjectHasTask::getDoneTasks( $id );


			$this->theme_view               = 'modal';
			$this->view_data['title']       = $this->lang->line( 'application_create_invoice' );
			$this->view_data['form_action'] = 'projects/invoice/' . $id;
			$this->content_view             = 'projects/_invoice';
		}
	}

	function activity( $id = false, $condition = false, $activityID = false )
	{
		$this->load->helper( 'notification' );
		$project = Project::find_by_id( $id );
		//$activity = ProjectHasAktivity::find_by_id($activityID);
		switch ( $condition ) {
			case 'add':
				if ( $_POST ) {
					unset( $_POST['send'] );
					$_POST['subject']    = htmlspecialchars( $_POST['subject'] );
					$_POST['message']    = strip_tags( $_POST['message'], '<br><br/><p></p><a></a><b></b><i></i><u></u><span></span>' );
					$_POST['project_id'] = $id;
					$_POST['user_id']    = $this->user->id;
					$_POST['type']       = "comment";
					unset( $_POST['files'] );
					$_POST['datetime'] = time();
					$activity          = ProjectHasActivity::create( $_POST );
					if ( ! $activity ) {
						$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_save_error' ) );
					} else {
						$this->session->set_flashdata( 'message', 'success:' . $this->lang->line( 'messages_save_success' ) );
						foreach ( $project->project_has_workers as $workers ) {
							send_user_notification(
								$this->user,
								$workers->user->email,
								"[" . $project->name . "] " . $_POST['subject'],
								$_POST['message'] . '<br><strong>' . $project->name . '</strong>',
								false,
								base_url() . 'projects/view/' . $id . '#activities-tab'
							);
						}
						if ( isset( $project->company->client->email ) ) {
							$access = explode( ',', $project->company->client->access );
							if ( in_array( '12', $access ) ) {
								send_user_notification(
									$this->user,
									$project->company->client->email,
									"[" . $project->name . "] " . $_POST['subject'],
									$_POST['message'] . '<br><strong>' . $project->name . '</strong>',
									false,
									base_url() . 'projects/view/' . $id . '#activities-tab'
								);
							}
						}
					}
					//redirect('projects/view/'.$id);

				}
				break;
			case 'update':

				break;
			case 'delete':
				$activity = ProjectHasActivity::find_by_id( $activityID );
				if ( $activity->user_id == $this->user->id ) {
					$activity->delete();
				}

				break;
		}

	}

	function view($id = false, $what = false, $taskId = false, $comment_guid = false) {
		$project = Project::find($id);
		$this->setTitle($project->name);

		$this->load->helper( 'file' );
		$this->view_data['submenu']              = array();
		$this->view_data['project']              = $project;
		//$this->view_data['go_to_taskID']         = $taskId;
		$this->view_data['first_project']        = Project::first();
		$this->view_data['last_project']         = Project::last();
		$this->view_data['project_has_invoices'] = Invoice::all( array(
			                                                         'conditions' => array(
				                                                         'project_id = ? AND estimate != ?',
				                                                         $id,
				                                                         1
			                                                         )
		                                                         ) );
		if ( ! isset( $this->view_data['project_has_invoices'] ) ) {
			$this->view_data['project_has_invoices'] = array();
		}
		$tasks                            = ProjectHasTask::count( array( 'conditions' => 'project_id = ' . $id ) );
		$this->view_data['alltasks']      = $tasks;
		$this->view_data['opentasks']     = ProjectHasTask::count( array(
			                                                           'conditions' => array(
				                                                           'status != ? AND project_id = ?',
				                                                           'done',
				                                                           $id
			                                                           )
		                                                           ) );
		$this->view_data['usercountall']  = User::count( array( 'conditions' => array( 'status = ?', 'active' ) ) );
		$this->view_data['usersassigned'] = ProjectHasWorker::count( array(
			                                                             'conditions' => array(
				                                                             'project_id = ?',
				                                                             $id
			                                                             )
		                                                             ) );

		$this->view_data['assigneduserspercent'] = round( $this->view_data['usersassigned'] / $this->view_data['usercountall'] * 100 );


		//Format statistic labels and values
		$this->view_data["labels"] = "";
		$this->view_data["line1"]  = "";
		$this->view_data["line2"]  = "";

		$daysOfWeek                         = getDatesOfWeek();
		$this->view_data['dueTasksStats']   = ProjectHasTask::getDueTaskStats( $id, $daysOfWeek[0], $daysOfWeek[6] );
		$this->view_data['startTasksStats'] = ProjectHasTask::getStartTaskStats( $id, $daysOfWeek[0], $daysOfWeek[6] );

		$history = ProjectChat::find( 'all', array(
			'conditions' => array( "project_id='" . $id . "'" ),
			'order'      => 'created desc',
			'limit'      => 100
		) );
		$newHist = [];
		foreach ( $history as $item ) {
			$chat = $item->to_array();

			if ( $item->from_external == 0 ) {
				$user = User::find( $chat['sender_id'] );
			} else {
				$user = User::find( $item->sender_id );
				if ( empty( $user ) ) {
					$link = SlackLink::find( 'first', array(
						'conditions' => array(
							"slack_id=? AND team_id=?",
							$item->slack_id,
							$item->team_id
						)
					) );
					if ( ! empty( $link->user_id ) ) {
						$user = User::find( $link->user_id );
					}
				}
			}
//            echo "<pre>"; print_r($user); echo "</pre>";
			$userpic_bot = '';
			if ( ! empty( $user ) ) {
				$username = $user->firstname . ' ' . $user->lastname;
				$userpic  = get_user_pic( $user->userpic, $user->email );
				if ( ! $userpic ) {
					$userpic_bot = strtoupper( substr( $user->firstname, 0, 1 ) . substr( $user->lastname, 0, 1 ) );
				}
			} else {
				$username = 'Unknown';
				$baseUrl  = 'https://spera-' . ENVIRONMENT . '.s3-us-west-2.amazonaws.com/' . $_SESSION['accountUrlPrefix'] . '/';
				$userpic  = $baseUrl . "files/media/no-pic.png";
			}
			$slackpic = '';
			if ( $item->from_external != 0 ) {
				$slackpic = "../../assets/blueline/img/slack_mark.png";
			}

			$phptime  = strtotime( $item->created );
			$viewtime = date( "Y-m-d H:i:s", $phptime );

			$chatItem = array(
				'avatar_url'   => $userpic,
				'avatar_str'   => $userpic_bot,
				'second_url'   => $slackpic,
				'user_name'    => $username,
				'is_me'        => ( ! empty( $user ) && $this->user->id == $user->id ),
				'chat_message' => $item->chat_message,
				'date_time'    => $viewtime
			);
			array_push( $newHist, $chatItem );
		}

		if($this->user){
			$myname    = $this->user->firstname . ' ' . $this->user->lastname;
			$mypic     = get_user_pic( $this->user->userpic, $this->user->email );
			$mypic_bot = false;
			if ( ! $mypic ) {
				$mypic_bot = strtoupper( substr( $this->user->firstname, 0, 1 ) . substr( $this->user->lastname, 0, 1 ) );
			}
		}elseif ($this->client) {
			$myname    = $this->client->firstname . ' ' . $this->client->lastname;
			$mypic     = get_user_pic( $this->client->userpic, $this->client->email );
			$mypic_bot = false;
			if ( ! $mypic ) {
				$mypic_bot = strtoupper( substr( $this->client->firstname, 0, 1 ) . substr( $this->client->lastname, 0, 1 ) );
			}
		}
		
		$myInfo = array(
			'avatar_url'   => $mypic,
			'avatar_str'   => $mypic_bot,
			'second_url'   => '',
			'user_name'    => $myname,
			'is_me'        => true,
			'chat_message' => '',
			'date_time'    => ''
		);

		$this->view_data['chatHistory'] = json_encode( $newHist );
		$this->view_data['myInfo']      = json_encode( $myInfo );

		foreach ( $daysOfWeek as $day ) {
			$counter  = "0";
			$counter2 = "0";
			foreach ( $this->view_data['dueTasksStats'] as $value ):
				if ( $value->due_date == $day ) {
					$counter = $value->tasksdue;
				}
			endforeach;
			foreach ( $this->view_data['startTasksStats'] as $value ):
				if ( $value->start_date == $day ) {
					$counter2 = $value->tasksdue;
				}
			endforeach;
			$this->view_data["labels"] .= '"' . $day . '"';
			$this->view_data["labels"] .= ',';
			$this->view_data["line1"]  .= $counter . ",";
			$this->view_data["line2"]  .= $counter2 . ",";

		}

		$this->view_data['time_days']       = round( ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) - human_to_unix( $this->view_data['project']->start . ' 00:00' ) ) / 3600 / 24 );
		$this->view_data['time_left']       = $this->view_data['time_days'];
		$this->view_data['timeleftpercent'] = 100;

		if ( human_to_unix( $this->view_data['project']->start . ' 00:00' ) < time() && human_to_unix( $this->view_data['project']->end . ' 00:00' ) > time() ) {
			$this->view_data['time_left']       = round( ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) - time() ) / 3600 / 24 );
			$this->view_data['timeleftpercent'] = $this->view_data['time_left'] / $this->view_data['time_days'] * 100;
		}
		if ( human_to_unix( $this->view_data['project']->end . ' 00:00' ) < time() ) {
			$this->view_data['time_left']       = 0;
			$this->view_data['timeleftpercent'] = 0;
		}

		if($this->user){
			$this->view_data['allmytasks']            = ProjectHasTask::all( array(
			                                                                 'conditions' => array(
				                                                                 'project_id = ? AND user_id = ?',
				                                                                 $id,
				                                                                 $this->user->id
			                                                                 )
		                                                                 ) );
			$this->view_data['mytasks']               = ProjectHasTask::count( array(
				                                                                   'conditions' => array(
					                                                                   'status != ? AND project_id = ? AND user_id = ?',
					                                                                   'done',
					                                                                   $id,
					                                                                   $this->user->id
				                                                                   )
			                                                                   ) );
			$this->view_data['tasksWithoutMilestone'] = ProjectHasTask::find( 'all', array(
				'conditions' => array(
					'milestone_id = ? AND project_id = ? ',
					'0',
					$id
				)
			) );
		}elseif ($this->client) {
			$this->view_data['allmytasks']            = ProjectHasTask::all( array(
			                                                                 'conditions' => array(
				                                                                 'project_id = ?',
				                                                                 $id
			                                                                 )
		                                                                 ) );
			$this->view_data['mytasks']               = ProjectHasTask::count( array(
				                                                                   'conditions' => array(
					                                                                   'status != ? AND project_id = ? ',
					                                                                   'done',
					                                                                   $id
				                                                                   )
			                                                                   ) );
			$this->view_data['tasksWithoutMilestone'] = ProjectHasTask::find( 'all', array(
				'conditions' => array(
					'milestone_id = ? AND project_id = ? ',
					'0',
					$id
				)
			) );
		}
		

		$tasks_done                  = ProjectHasTask::count( array(
			                                                      'conditions' => array(
				                                                      'status = ? AND project_id = ?',
				                                                      'done',
				                                                      $id
			                                                      )
		                                                      ) );
		$this->view_data['progress'] = $this->view_data['project']->progress;
		if ( $this->view_data['project']->progress_calc == 1 ) {
			if ( $tasks ) {
				@$this->view_data['progress'] = round( $tasks_done / $tasks * 100 );
			}
			$attr = array( 'progress' => $this->view_data['progress'] );
			$this->view_data['project']->update_attributes( $attr );
		}
		@$this->view_data['opentaskspercent'] = ( $tasks == 0 ? 0 : $tasks_done / $tasks * 100 );

		if($this->user){
			$projecthasworker = ProjectHasWorker::all( array(
			                                           'conditions' => array(
				                                           'user_id = ? AND project_id = ?',
				                                           $this->user->id,
				                                           $id
			                                           )
		                                           ) );
			@$this->view_data['worker_is_client_admin'] = CompanyHasAdmin::all( array(
				                                                                    'conditions' => array(
					                                                                    'user_id = ? AND
			 company_id = ?',
					                                                                    $this->user->id,
					                                                                    $this->view_data['project']->company_id
				                                                                    )
			                                                                    ) );
			if ( ! $projecthasworker && $this->user->admin != 1 && ! $this->view_data['worker_is_client_admin']) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_no_access_error' ) );
				redirect( 'projects' );
			}
		}elseif ($this->client) {
			$projecthasworker = ProjectHasWorker::all( array(
			                                           'conditions' => array(
				                                           'project_id = ?',
				                                           $id
			                                           )
		                                           ) );
			@$this->view_data['worker_is_client_admin'] = CompanyHasAdmin::all( array(
				                                                                    'conditions' => array(
					                                                                    'company_id = ?',
					                                                                    $this->view_data['project']->company_id
				                                                                    )
			                                                                    ) );
			if ( ! $projecthasworker &&  ! $this->view_data['worker_is_client_admin']) {
				$this->session->set_flashdata( 'message', 'error:' . $this->lang->line( 'messages_no_access_error' ) );
				redirect( 'projects' );
			}
			
		}
		
		$tracking = $this->view_data['project']->time_spent;
		if ( ! empty( $this->view_data['project']->tracking ) ) {
			$tracking = ( time() - $this->view_data['project']->tracking ) + $this->view_data['project']->time_spent;
		}
		$this->view_data['timertime']             = $tracking;
		
		if($this->user){
			$this->view_data['timertime']             = ProjectUserTimeTracking::getTotalTimeSpent( $id, $this->user->id );
			$this->view_data['is_tracking']           = ProjectUserTimeTracking::isTracking( $id, $this->user->id );
		}elseif($this->client){

		}
		
		$this->view_data['time_spent_from_today'] = time() - $this->view_data['project']->time_spent;
		$tracking                                 = floor( $tracking / 60 );
		$tracking_hours                           = floor( $tracking / 60 );
		$tracking_minutes                         = $tracking - ( $tracking_hours * 60 );


		$this->view_data['time_spent']         = $tracking_hours . " " . $this->lang->line( 'application_hours' ) . " " . $tracking_minutes . " " . $this->lang->line( 'application_minutes' );
		$this->view_data['time_spent_counter'] = sprintf( "%02s", $tracking_hours ) . ":" . sprintf( "%02s", $tracking_minutes );
		$this->view_data['comment_id']         = '-1';

		if ( $comment_guid && $taskId ) {
			$comment_guid = $taskId . '-' . $comment_guid;
			$mention      = Mention::find( 'all', array( 'conditions' => array( 'guid=?', $comment_guid ) ) );

			if ( isset( $mention[0] ) ) {
				$this->view_data['comment_id'] = $mention[0]->item_id;
			}
		}
		if($this->user){
			$this->view_data['my_id'] = $this->user->id;	
		}elseif ($this->client) {
			$this->view_data['my_id'] = $this->client->id;
		}
		
		$this->view_data['id'] = $id;

		switch($what) {
			case "tasks":
				$this->content_view = "projects/project";
				break;
			case "team":
				$this->content_view = "projects/project";
				break;
			case "gantt":
				$this->content_view = 'projects/gant';
				break;
			case "files":
				$this->content_view = 'projects/files';
				break;
			case "notes":
				$this->content_view = 'projects/notes';
				break;
			case "invoices":
				$this->content_view = 'projects/invoices';
				break;
		}
	}

	public function  gettasknotifiction(){
		header('Content-Type', 'application/json');

		$notifictions = ProjectHasTask::find('all', array('conditions' => array('user_id = ? AND tracking != ?', $this->user->id, 0)));
		$notifictions = array_map( function ( $notification ) {

			$attributes = $notification->attributes();
			$attributes['project_name'] = $notification->project->name;
			$attributes['time_track'] = ( time() - $notification->tracking ) + $notification->time_spent;
			return $attributes;
		}, $notifictions );

		echo json_encode($notifictions);
		die();
	}
}