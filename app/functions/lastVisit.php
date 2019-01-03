<?
    function lastVisit($job_id){
        $query = "select * from maintenance where job_id = $job_id order by maintenance_date DESC limit 1";
        $lastVisit = mysqli_fetch_array(query($query));
        return $lastVisit['maintenance_date'];
    }
?>