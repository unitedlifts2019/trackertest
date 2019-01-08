<?php
        /*
                Jobs Controller
                Version: 14.2.24
                Cody Joyce
        */

        $jobs = new jobs();
        class jobs{
                function index()
                {
                        $where = "";
                        
                        if(req("round_id")){
                                $where = " where jobs.round_id = ".req("round_id");
                        }
                        
                        $data = array(
                                "result" => query("select 
                                        job_id,job_suburb,job_number,job_agent_contact,agent_name,status_name,job_name,job_floors,job_address,job_address_number,job_contact_details,job_owner_details,job_group,round_name,round_colour,
                                        (select count(*) from lifts where lifts.job_id= jobs.job_id) as lift_count
                                        from jobs 
                                        inner join agents on jobs.agent_id = agents.agent_id
                                        inner join _status on jobs.status_id = _status.status_id
                                        inner join rounds on jobs.round_id = rounds.round_id
                                        $where
                                ")
                        );                      
                        view("jobs/jobs_table",$data);
                }
                
                function form()
                {
                        $job = _getValues("jobs","job_id");

                        $query = "select * from rounds";
                        $allRounds = query($query);

                        $result = query("select * from rounds where round_id = ".$job["round_id"]);
                        $round=null;
                        
                        if($job["start_time"] > 0)
                                $job["start_time"] = toDateTime($job["start_time"]);

                        if($job["finish_time"] > 0)
                                $job["finish_time"] = toDateTime($job["finish_time"]);
							
							if($job["cancel_time"] > 0)
                                $job["cancel_time"] = toDateTime($job["cancel_time"]);
							
						if($job["active_time"] > 0)
                                $job["active_time"] = toDateTime($job["active_time"]);
						
						if($job["inactive_time"] > 0)
                                $job["inactive_time"] = toDateTime($job["inactive_time"]);

                        if(is_object($result))
                                $round = mysqli_fetch_array($result);

                        $data = array(
                                "job" => $job,
                                "round" =>$round,
                                "allRounds"=>$allRounds
                        );

                        view("jobs/jobs_form",$data);                                            
                }
                
                function printJobs()
                {
                        $where = "";
                        if(req("round_id")){
                        $where = " where jobs.round_id = ".req("round_id");
                        }
                        $data = array(
                        "result" => query("select 
                        job_id,job_suburb,job_number,jobs.status_id,job_agent_contact,job_key_access,agent_name,status_name,job_name,job_floors,job_address,job_address_number,job_contact_details,job_owner_details,job_group,round_name,round_colour,start_time,finish_time,price,
                        (select count(*) from lifts where lifts.job_id= jobs.job_id) as lift_count
                        from jobs 
                        inner join agents on jobs.agent_id = agents.agent_id
                        inner join _status on jobs.status_id = _status.status_id
                        inner join rounds on jobs.round_id = rounds.round_id
                        $where order by job_number,job_address,job_address_number ASC
                        ")
                        );                      
                        view_plain("jobs/jobs_print",$data);
                }
                
                function printAccess()
                {
                        $where = "";
                        
                        if(req("round_id")){
                                $where = " where jobs.round_id = ".req("round_id");
                        }

                        $orderby = "order by job_number ASC";

                        if(req('sort')){
                                $orderby = "order by job_address,job_address_number ASC";
                        }

                        $data = array(
                                "result" => query("select 
                                        job_id,job_suburb,job_number,jobs.status_id,job_agent_contact,job_key_access,agent_name,status_name,job_name,job_floors,job_address,job_address_number,job_contact_details,job_owner_details,job_email,job_group,round_name,round_colour,
                                        (select count(*) from lifts where lifts.job_id= jobs.job_id) as lift_count
                                        from jobs 
                                        inner join agents on jobs.agent_id = agents.agent_id
                                        inner join _status on jobs.status_id = _status.status_id
                                        inner join rounds on jobs.round_id = rounds.round_id
                                        where job_key_access <>  '' $orderby
                                ")
                        );                      
                        
                        view_plain("jobs/jobs_access",$data);
                }
                
                function callouts()
                {
                        $id = req("frm_job_id");
                        $query = "select * from callouts_view where job_id = $id order by callout_time DESC";
                        $results = query($query);
                        $data = array(
                                "results"=>$results
                        );
                        
                        view_plain("jobs/jobs_callouts",$data);
                }
                
                function maintenance()
                {
                        $id = req("frm_job_id");
                        $query = "select * from maintenance_view where job_id = $id order by maintenance_date DESC";
                        $results = query($query);
                        $data = array(
                                "results"=>$results
                        );
                        
                        view_plain("jobs/jobs_maintenance",$data);                       
                }
				
		function repair()
                {
                        $id = req("frm_job_id");
                        $query = "select * from repairs where job_id = $id order by repair_time DESC
                                  ";
                        $results = query($query);
                        $data = array(
                                "results"=>$results
                        );
                        
                        view_plain("jobs/jobs_repair",$data);                       
                }
                
                function action()
                {
                        $url = app('url');
                        
                        
                        if(req("frm_notify_instant"))
                        {
                                req("frm_notify_instant",1);
                        }else{
                                $_REQUEST["frm_notify_instant"]=0;
                        }

                        req("frm_start_time",strtotime(req("frm_start_time")));   
                        req("frm_finish_time",strtotime(req("frm_finish_time")));
						req("frm_cancel_time",strtotime(req("frm_cancel_time")));
						req("frm_active_time",strtotime(req("frm_active_time")));
						req("frm_inactive_time",strtotime(req("frm_inactive_time")));
						
		    if($_FILES['file']){
                    if(uploadFile(app('app_path')."/uploads/")){
                    req("frm_job_attatch",basename($_FILES['file']['name']));
                    }
                }
				
		    if($_FILES['file2']){
                    if(uploadFile(app('app_path')."/uploads/")){
                    req("frm_cancel_file",basename($_FILES['file2']['name']));
                    }
                }
                        
                        //geolocate the address automagially if GPS coordinates are not included in the form submission
                        if(req("frm_job_latitude")==""){
                                $address = req("frm_job_address_number") ." ".req("frm_job_address") . " " . req("frm_job_suburb") . " Australia";
                                $gps = explode(" ",geolookup($address));

                                req("frm_job_latitude",$gps[1]);
                                req("frm_job_longitude",$gps[0]);
                        }
                        
                        
                        $alert = _submitForm("jobs","job_id");

                        if(req("frm_job_id")){ 
                                redirect("$url/exec/jobs/form/?alert=$alert&frm_job_id=".req("frm_job_id"));
                        }else{ 
                                redirect("$url/exec/jobs/?alert=$alert");
                        }
                }
                
                function delete()
                {
                        $url = app("url");
                        $job_id = req("frm_job_id");
                        $query = "delete from jobs where job_id = $job_id";
                        query($query);
                        $query = "delete from callouts where job_id = $job_id";
                        query($query);
                        redirect("$url/exec/jobs/?alert=Job and callouts Deleted");
                }
        }
?>
