<?
    $reports = new reports();
    class reports{
        function index()
        {
            $data = array(
                "result" => query("select * from reports")
            );			
            view("reports/reports_home",$data);
        }
        
        function site()
        {
            $data = array(
                "jobs" => query("select * from jobs order by job_address,job_address_number asc")
            );			
            view("reports/reports_site_form",$data);
        }

        function group()
        
        {
            $query = "select distinct job_group from jobs where status_id = 1";
            $result = query($query);
            $group_list = array();
            
            //Generate a list of groups for the form
            while($row = mysqli_fetch_array($result)){
                $group_names = explode(",",$row["job_group"]);

                foreach($group_names as $group_name){
                    if($group_name){
                        $group_list["$group_name"] = $group_name;
                    }
                }
            };
            
            $data = array(
                "groups"=>$group_list
            );
            
            view("reports/reports_group_form",$data);
        }
        
        function callouts()
        {
            $data = array(
                "allRounds" => query("select * from rounds where status_id = 1")
            );

            view("reports/reports_callouts_form",$data);
        }
        
        function callouts_generate()
        {
            //Get the paramerers from the form
                $start_date = strtotime(req("frm_start_date"));
                $end_date = strtotime(req("frm_end_date"));
                $round_id = req("frm_round_id");
                $orderby = req("frm_orderby");
            
                $round = "";
                if(req("frm_round_id"))
                    $round = " AND round_id = $round_id ";     

                $order = "";
                if(req("frm_orderby"))
                    $order = " order by $orderby DESC";
            
            //create a temporary table for storing / sorting data
                $random = rand(1111,9999);
                $query ="CREATE TEMPORARY TABLE temp_callouts_report_$random(
                           `job_id` INT NULL,
                           `lift_count` INT NULL,
                           `call_count` INT NULL,
                           `call_average` DECIMAL(6,2) NULL
                        )";
                query($query);

            //Loop through each job
                $query = "select * from jobs where status_id = 1 $round";
                $result = query($query);
                $faults = array();
				$callCountTotal = 0;
				
                while($row = mysqli_fetch_array($result))
                {
                    $job_id = $row["job_id"];
                    
                    //number of lifts the job bas
                    $query = "select * from lifts where job_id = $job_id";
                    $liftCount = mysqli_num_rows(query($query));
                    
                    //number of calls the job has
                    $query = "select * from callouts_view where job_id = $job_id and callout_time > $start_date AND callout_time < $end_date";
                    $callouts = get_query($query);
					$callCount = count($callouts);
					$callCountTotal = $callCountTotal + $callCount;
					
					foreach($callouts as $callout){
						if(isset($faults[$callout["fault_name"]])){
							$faults[$callout["fault_name"]]++;
						}else{
							$faults[$callout["fault_name"]]=1;
						}
					}
                    
                    //work out the call average per unit.
                    $callAverage = 0;
                    if($callCount>0 && $liftCount>0)
                        $callAverage = $callCount / $liftCount;

                    //put the data we collected into the temporary table
                    $query ="insert into temp_callouts_report_$random 
                        (job_id,lift_count,call_count,call_average) 
                        VALUES
                        ($job_id,$liftCount,$callCount,$callAverage)";
                    
					if($callCount>0)
					query($query);
                }
            
            //select the temporary table by order
                $query = "select * from temp_callouts_report_$random 
                            inner join jobs on temp_callouts_report_$random.job_id = jobs.job_id
                            inner join rounds on jobs.round_id = rounds.round_id
                            $order
                         ";
                $results = query($query);


                
                $data = array(
                    "results"=>$results,
					"faults"=>$faults,
					"callCountTotal"=>$callCountTotal
                    
                );
                view_plain("reports/reports_callouts_generate",$data);
        }
        
        function group_generate()
        {
            $group_name = req("frm_group_name");
            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));
            
            $query = "select * from callouts_view 
				where job_group like '%$group_name%' 
				AND callout_time > $start_date 
				AND callout_time < $end_date
				AND callout_status_id = 2
				ORDER BY callout_time DESC
				";    
            // echo $query;
            
            if(req("frm_fault_id"))
                $query .= " AND fault_id = ".req("frm_fault_id");
            
            if(req("frm_order_by"))
                $query .=" order by ".req("frm_order_by")." ".req("frm_direction");
			
			$faults = array();
			
			$callouts = get_query($query);
            
            $maintenance = get_query("select * from maintenance_view 
                                        where job_group like '%$group_name%' 
                                        AND maintenance_date > $start_date 
                                        AND maintenance_date < $end_date
                                        AND technician_id <> 41 order by job_address,job_address_number,maintenance_date");
                                                                             
			foreach($callouts as $callout){
				if(isset($faults[$callout["fault_name"]])){
					$faults[$callout["fault_name"]]++;
				}else{
					$faults[$callout["fault_name"]]=1;
				}
			}
			
            $data = array (
                "callouts"=>$callouts,
                "start_date"=>$start_date,
                "end_date"=>$end_date,
				"group_name"=>$group_name,
				"faults"=>$faults,
                "maintenance"=>$maintenance
            );

            view_plain("reports/reports_group_generate",$data);
// echo "select * from callouts_view 
// 				where job_group like '%$group_name%' 
// 				AND callout_time > $start_date 
// 				AND callout_time < $end_date
// 				AND callout_status_id = 2
				
// 				ORDER by job_address,job_address_number,callout_time";			
        }
        
        function group_maintenance_generate()
        {
            $group_name = req("frm_group_name");
            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));
            
            $task_id = req('frm_task_id');
            $tasks='';
            
            if(req('frm_task_id'))
                $tasks = "AND task_ids like '%|$task_id|%'";
            
            $query = "select * from maintenance_view 
                                        where job_group like '%$group_name%' 
                                        AND maintenance_date > $start_date 
                                        AND maintenance_date < $end_date
                                        $tasks
                                        order by job_address,job_address_number";
           // echo $query;
            $maintenance = get_query($query);


            $data = array (
                "start_date"=>$start_date,
                "end_date"=>$end_date,
				"group_name"=>$group_name,
                "maintenance"=>$maintenance
            );

            view_plain("reports/reports_group_maintenance_generate",$data);
        }        

        function site_generate()
        {
            $job_id = req("frm_job_id");
            $job = mysqli_fetch_array(query("select * from jobs where job_id = $job_id"));
            
            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));
            $fault_id = req("frm_fault_id");
            
            $query = "select * from callouts_view where 
            callout_time > $start_date AND 
            callout_time < $end_date AND 
            job_id = $job_id";
            
            //foreach($_REQUEST as $req=>$val)
            //{
            //    if(strstr($req,"lift_")){
            //        $query .= " AND lift_ids like '%|$val|%' ";
            //    }
			//}
            
            //if(req("frm_fault_id"))
            //   $query .= " AND fault_id = $fault_id ";
            
            //if(req("frm_order_by"))
            //    $query .=" order by ".req("frm_order_by")." ".req("frm_direction");
            
            $agent_id = $job["agent_id"];
            $agent = mysqli_fetch_array(query("select * from agents where agent_id = $agent_id"));
            
            //get results for the graph

			$result = get_query($query);
            
			$faults = array();
            
			
            foreach($result as $row)
            {
                if(!isset($faults[$row['fault_name']]))
                                $faults[$row['fault_name']] = 0;
				$faults[$row['fault_name']]++;
            }

            $data = array (
                "callouts"=>query($query),
                "job"=>$job,
                "agent"=>$agent,
                "start_date"=>$start_date,
                "end_date"=>$end_date,
                "faults"=>$faults
            );

            view_plain("reports/reports_site_generate",$data);
        }

        function maintenance()
        {

            
            $data = array(
               
                "jobs" => query("select * from jobs where status_id= 1 order by job_address,job_address_number asc")
            );			
            view("reports/reports_maintenance_form",$data);
        }        
        
        function maintenance_generate()
        {
            $job_id = req("frm_job_id");
            $job = mysqli_fetch_array(query("select * from jobs where job_id = $job_id"));
            
            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));
            $fault_id = req("frm_fault_id");
            
            $query = "select * from maintenance
            inner join technicians on maintenance.technician_id = technicians.technician_id
            where maintenance_date >= $start_date AND maintenance_date < $end_date AND job_id = $job_id AND completed_id = 2";

            
            foreach($_REQUEST as $req=>$val)
            {
                if(strstr($req,"lift_")){
                    $query .= " AND lift_ids like '%|$val|%' ";
                }
            }
            
            if(req("frm_task_id"))
                $query .= " AND task_ids like '%|".req("frm_task_id")."|%'";
            
            if(req("frm_order_by"))
                $query .=" order by ".req("frm_order_by")." ".req("frm_direction");
            
            $agent_id = $job["agent_id"];
            $agent = mysqli_fetch_array(query("select * from agents where agent_id = $agent_id"));
            
            //get results for the graph

			$result = get_query($query);
            //echo $query;

            //echo $query;
            
            $data = array (
                "callouts"=>query($query),
                "job"=>$job,
                "agent"=>$agent,
                "start_date"=>$start_date,
                "end_date"=>$end_date
            );

            view_plain("reports/reports_maintenance_generate",$data);
        }
        
        function pits()
        {		
            view("reports/reports_pits_form",null);
        }        
        
        function pits_generate()
        {

            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));

            
            $query = "select * from maintenance_view
            
            
			
			where maintenance_date >= $start_date 
			AND maintenance_date < $end_date
			AND job_group LIKE '%Pit Clean%'
			AND maintenance_notes like '%pits%'
			order by job_address,job_address_number ASC";


            $data = array (
                "callouts"=>query($query),
                "start_date"=>$start_date,
                "end_date"=>$end_date
            );

            view_plain("reports/reports_pits_generate",$data);
        }     


        function weekly()
        {		
            view("reports/reports_weekly_form",null);
        }        
        
        function weekly_generate()
        {

            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));

            
            $query = "select * from callouts
            inner join jobs on callouts.job_id = jobs.job_id
			where callout_time >= $start_date 
			AND callout_time < $end_date";

            //$num_rows = mysql_num_rows($query);

            $data = array (
                "callouts"=>query($query),
                "start_date"=>$start_date,
                "end_date"=>$end_date,
                //"num_rows"=>$num_rows
            );

            view_plain("reports/reports_weekly_generate",$data);
        }    
		
				
		function jobs(){
			
			$data = array(
				"allJobs" => query("select * from jobs")
			);
			
			view_plain("reports/reports_job_generate",$data);
		}
		
        function printReport()
        {
			//we use $_REQUEST here because we dont want any special filtering.
            $contents = $_REQUEST["frm_contents"];
            $contents = str_replace('\r\n',"",$contents);
            $contents = stripslashes($contents);

            define("DOMPDF_ENABLE_HTML5PARSER", true);
define("DOMPDF_ENABLE_FONTSUBSETTING", true);
define("DOMPDF_UNICODE_ENABLED", true);
define("DOMPDF_DPI", 160);
define("DOMPDF_ENABLE_REMOTE", true);

            require_once(app('lib_path')."/functions/dompdf/dompdf_config.inc.php");
            $dompdf = new DOMPDF();
            $dompdf->load_html($contents);
            $dompdf->set_paper('a4', 'landscape');
            $dompdf->render();
            $dompdf->stream(req("frm_filename"));         
        }         
    
        function lifts()
        {
            $data = array(
                "allRounds" => query("select * from rounds where status_id = 1")
            );

            view("reports/reports_lifts_form",$data);            
        }

        
        function lifts_generate()
        {
                //Get the parameters from the form
                $start_date = strtotime(req("frm_start_date"));
                $end_date = strtotime(req("frm_end_date"));

                //create a temporary table for storing / sorting data
                $random = rand(1111,9999);
                $query ="CREATE TEMPORARY TABLE temp_lifts_report_$random(
                           `lift_id` INT NULL,
                           `job_name` TEXT NULL,
                           `call_count` INT NULL
                        )";
                query($query);
                
                
                $result = query("
                    select * from lifts
                    inner join jobs on lifts.job_id = jobs.job_id 
                    where lifts.status_id = 1 
                    AND jobs.status_id = 1
                    order by lift_id ASC
                ");
                
                while($row = mysqli_fetch_array($result)){
                    $lift_id = $row["lift_id"];
                    $job_id = $row["job_id"];
                    
                    $callouts = query("select * from callouts 
                        inner join jobs on callouts.job_id = jobs.job_id
                        where callout_time > $start_date
                        AND callout_time < $end_date
                        AND lift_ids LIKE '%|$lift_id|%'
                    ");
                    
                    $call = mysqli_fetch_array($callouts);
                    $count = mysqli_num_rows($callouts);
                   
                    if($count > 0){
                        $lift_id = $row["lift_id"];
                        $job_name = $call["job_name"];
                       
                        
                        $query = "insert into temp_lifts_report_$random (lift_id,job_name,call_count)
                        VALUES ($lift_id,'$job_name',$count);";
                        query($query);
                    }
                }

                $data = array(
                    "liftcalls" => query("select * from temp_lifts_report_$random order by call_count DESC"),
                    "start_date" => $start_date,
                    "end_date" => $end_date
                );
                
                view_plain("reports/reports_lifts_generate",$data);    
        }
        
        
        function monthly()
        {
            $start_date = strtotime(req("frm_start_date"));
            $end_date = strtotime(req("frm_end_date"));
            
            $query = 
            "
                select 
                    job_id,
                    jobs.round_id,
                    jobs.frequency_id,
                    job_number,
                    job_address,
                    job_address_number,
                    job_name,
                    rounds.round_name,
                    rounds.round_colour,
                    _frequency.frequency_name,
                    _frequency.frequency_value,
                    
                    (select count(*) from callouts where job_id = jobs.job_id) as call_count,
                    (select maintenance_date from maintenance where job_id = jobs.job_id order by maintenance_date DESC limit 1) as last_serviced,
                    (select UNIX_TIMESTAMP(NOW())-last_serviced) as time_since_service
                from jobs 
                    inner join rounds on jobs.round_id = rounds.round_id
                    inner join _frequency on jobs.frequency_id = _frequency.frequency_id
                where 
                    jobs.status_id = 1
                order by 
                    job_address,job_address_number ASC
            ";
            
            $jobs=get_query($query);
            
            $data = array(
                "jobs" => $jobs
            );
            
            view_plain("reports/reports_monthly_generate",$data);    
        }
    }
    
?>
