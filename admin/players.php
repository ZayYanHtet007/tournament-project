  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<?php
include('sidebar.php');
?>
 <!-- <?php
    include("../database/dbConfig.php");
 ?>  -->


<?php
$sql="select * from users ";
$result=mysqli_query($conn,$sql);

?>
 
  <table class="table table-dark table-striped">
    <thead>
      <tr>
        <th>Profile_img</th>
        <th>Players-name</th>
        <th>Email</th>
        <th>created at</th>
        <th>last update</th>
        <th>Ban</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $sql="select * from users";
    $users=mysqli_query($conn,$sql);
    while($row=mysqli_fetch_assoc(($users))){?>
  <tr>
          <td><img src="../images/<?= $row['profile_img']?>"malt="" class="users_img"></td>
        <td><?= $row['username']?></td>
         <td><?= $row['email']?></td>
         <td><?= $row['created at']?></td>
         <td><?= $row['last update ']?></td>
        <td><a $href="" class="btn_in"> InActive</a></td>

      </tr>
   <?php }
    ?>
   
    </tbody>
  </table> 


<?php
include('footer.php');
?>