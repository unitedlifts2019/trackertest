<?
	$maintenance = new maintenance();
	class maintenance{
		
        function index()
        {
                $query = "select * from maintenance
                        inner join jobs on maintenance.job_id = jobs.job_id
                        inner join technicians on maintenance.technician_id = technicians.technician_id
                        where maintenance.completed_id=2 order by maintenance_date DESC limit 0,1000";
                            
                $data = array(
                        "result" => get_query($query)
                );		         
 
                view("maintenance/maintenance_table",$data);
        }
		
        function form()
        {
            $values = _getValues("maintenance","maintenance_id");
            
            if($values["maintenance_date"]==""){
                $values["maintenance_date"] = date("d-m-Y");
                if(is_mobile()==true){
					$values["maintenance_date"] = date("Y-m-d");
					$values["maintenance_date"] = str_replace(" ","T",$values["maintenance_date"]);
				}
				
				$values["completed_id"] = 2;
            }else{
                $values["maintenance_date"] = toDateTime($values["maintenance_date"]);
            }
			
			if($values["maintenance_toa"] > 0)
				$values["maintenance_toa"] = toDateTime($values["maintenance_toa"]);

			if($values["maintenance_tod"] > 0)
				$values["maintenance_tod"] = toDateTime($values["maintenance_tod"]);				

			$tasks = get_query("select * from _new_tasks");
            //if(req("esculator"))
				//$tasks = get_query("select * from _new_tasks where task_id > 1000");
            
            $data = array(
               "values"=>$values, 
               "tasks"=>$tasks
            );
			
            view("maintenance/maintenance_form",$data);                                            
        }
		
		function action()
		{
				$url = app('url');
				req('frm_maintenance_date',strtotime(req('frm_maintenance_date')));
				req('frm_updated',time());
					//Do some work to get the checked lift boxes
                        $lift_ids = "";
                        foreach ($_REQUEST as $req_var => $req_val) {
                            if(strstr($req_var,"lift_")){
                                $lift_ids.= "|".$req_val."|";
                            }
                        }
                        $lift_ids = str_replace("||","|",$lift_ids);
                        req("frm_lift_ids",$lift_ids);
                    
  //working area -------------------------------                  
                        req("frm_service_area_ids","|0|");
                        req("frm_service_type_ids","|0|"); 
                        
                        $task_ids = "";
                        foreach ($_REQUEST as $req_var => $req_val) {
                            if(strstr($req_var,"task_")){
                                $task_ids.= "|".$req_val."|";
                            }
                        }
                        $task_ids = str_replace("||","|",$task_ids);
                        req("frm_task_ids",$task_ids);                        
                         
  //working area -------------------------------                      
                    
                    //change date strings to timestamp
                    req('frm_maintenance_toa',strtotime(req('frm_maintenance_toa')));
                    req('frm_maintenance_tod',strtotime(req('frm_maintenance_tod')));
					req("frm_user_id",sess("user_id"));
                
				_submitForm("maintenance","maintenance_id");
                        
                        //EMAIL PART
                        include(app('path')."\\app\\views\\maintenance\\maintenance_email.php");  
                        
				//now we add X days to the date according to the frequency specified initially
				if(req('schedule')>0){
                    req('frm_maintenance_date',strtotime(req('schedule')));
                    req('frm_completed_id',1);
                    req('frm_maintenance_notes','');
                    req('frm_maintenance_toa',' ');
                    req('frm_maintenance_tod',' ');                    
                    $alert = _submitForm("maintenance","maintenance_id");
                    
                
                }

				if(req("frm_maintenance_id")){ 
					redirect("$url/exec/maintenance/form/?alert=Maintenance Call Updated&frm_maintenance_id=".req("frm_maintenance_id"));
				}else{     
					redirect("$url/exec/maintenance/?alert=$alert");
				}
		}
		
		function delete()
		{
			$id = req('frm_maintenance_id');
			$query = "delete from maintenance where maintenance_id = $id";
			query($query);
			redirect(app('url')."/exec/maintenance/");
		}
		
        function getAreas()
        {
            $areas = query("select * from _service_areas");
            
            $x=1;
            while($area = mysqli_fetch_array($areas))
            {
                $area_name = $area["service_area_name"];
                $checked = "";
                
                if(strstr(req("frm_service_area_ids"),"|".$area["service_area_id"]."|")){
                    $checked = "CHECKED";   
                }
                echo "<input class='check liftcheck' type='checkbox' $checked name='service_area_$x' value='".$area["service_area_id"]."'>".$area["service_area_name"];
                echo "<br>";
                $x++;
            }            
        }

        function getTypes()
        {
            $types = query("select * from _service_types");
            
            $x=1;
            while($type = mysqli_fetch_array($types))
            {
                $type_name = $type["service_type_name"];
                $checked = "";
                
                if(strstr(req("frm_service_type_ids"),"|".$type["service_type_id"]."|")){
                    $checked = "CHECKED";   
                }
                echo "<input class='check liftcheck' type='checkbox' $checked name='service_type_$x' value='".$type["service_type_id"]."'>".$type["service_type_name"];
                echo "<br>";
                $x++;
            }            
        }        
        
        function schedule()
        {
                $query = "select * from maintenance_view
                                        
                                        where completed_id=1
                                ";
        
                $calls = get_query($query);
                
                $data = array(
                        "calls"=>$calls
                );
                view("maintenance/maintenance_schedule",$data);
        }
		
        function rollover()
        {
			$today = strtotime(toDate(time()));

			$query = "select * from maintenance where maintenance_date = $today and completed = 0";
			$mcalls = get_query($query);
			
			foreach($mcalls as $call){
				
				//get todays maintenance call details
				$maintenance_id = $call['maintenance_id'];
				$maintenance_date = $call['maintenance_date'];
				$technician_id = $call['technician_id'];
				$job_id = $call['job_id'];
                                $lift_ids = $call['lift_ids'];
				$service_area_id = $call['service_area_id'];
				$service_type_id = $call['service_type_id'];
				$frequency_id = $call['frequency_id'];
				
				//calculate when the next maintenance call is by frequency
				$query = "select frequency_value from _frequency where frequency_id = $frequency_id";
				$frequency = get_query($query);
				$days = $frequency[0]['frequency_value'] * 86400;
				$future_date = $maintenance_date + $days;
				
				$query = "insert into maintenance (maintenance_date,technician_id,job_id,lift_ids,service_area_id,service_type_id,frequency_id,notes,completed)
										VALUES	  ($future_date,$technician_id,$job_id,'$lift_ids',$service_area_id,$service_type_id,$frequency_id,'',0)
				";
                
                query($query);
			}
		}
        
        function printPdf()
        {
            
            $maintenance_id = req("frm_maintenance_id");
            
            $query = "select * from maintenance inner join jobs on maintenance.job_id = jobs.job_id
            inner join technicians on maintenance.technician_id = technicians.technician_id where maintenance_id = $maintenance_id";
            $visit = mysqli_fetch_array(query($query));
            
            $job_id = $visit['job_id'];
            $lifts = get_query("select * from lifts where job_id = $job_id");

            $data = array(
                                "visit" => $visit,
                                "lifts"=>$lifts
            );
            
            view_plain("maintenance/maintenance_print",$data);        
        }
        
        function checkDocket()
        {
                $docket_number = req('docket_number');
                $query = "select * from maintenance where docket_no = '$docket_number'";
                $result = get_query($query);
                if(sizeof($result)>0){
                        echo "1";
                }else{
                        echo 0;
                }
        }
}
?>
