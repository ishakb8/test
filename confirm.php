<?php
 include('admin/includes/connect.php');
 $passkey = $_GET['passkey'];
 $sql = "UPDATE ww_users SET activation_link='Active' WHERE activation_link='$passkey'";
 $result = mysql_query($sql) or die(mysql_error());
 
?>
<!DOCTYPE html>
<html lang="en">
  <head>
   <?php include('includes/meta.php'); ?>
    
    <!-- icons -->

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="../../https@oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="../../https@oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

  </head>
  <body>
	<!-- header -->
	<?php include('includes/header.php'); ?>
	<!-- header -->

	<!-- signin-page -->
	<section id="main" class="clearfix user-page">
		<div class="container">
			<div class="row text-center">
				<!-- user-login -->			
				<div class="col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3">
				<?php if($result)
 {

?>	<div style="max-width:530px; min-width:280px; overflow:hidden; text-align:center;     margin-top: 50px; font-family:Arial, Helvetica, sans-serif;">

<div style="background:rgba(240, 86, 87, 0.6); padding:20px; overflow:hidden; text-align:center; margin:10px 0px 0px 0px;">
<div style="background:#ffffff; padding:25px 30px;">
    	<span style="font-size:22px; color:#000000; font-weight:bold; line-height:30px;">Your account is now active. You may click below..</span>
        <a href="login.php" style="color:#ffffff; text-decoration:none; font-weight:bold; font-size:22px;">
        <div style="display:block; background:#f15757; padding:10px 0px; width:100%; margin:10px 0px;">Click Here Login</div>
        </a>
    </div>
</div>
</div>
<?php }
 else
 {
  echo "Some error occur.";
 } ?>
					
				</div><!-- user-login -->			
			</div><!-- row -->	
		</div><!-- container -->
	</section><!-- signin-page -->
	<br/><br/> <br/><br/>
	<!-- footer -->
	<?php include('includes/footer.php'); ?>
	
  </body>
</html>