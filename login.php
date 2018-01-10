<?php ob_start();
session_start();
include('admin/includes/connect.php');
$page_details_qry=mysql_fetch_assoc(mysql_query("SELECT * FROM ww_pages where page_id=1"));
$site_details_qry=mysql_fetch_assoc(mysql_query("select * from ww_site where si_id=1"));
//Sign up Starts
if(isset($_POST['register_submit']))
{
mysql_real_escape_string(extract($_POST));
$activation_link = md5(uniqid(rand()));
$email = $_POST['email'];
 $password=md5($_POST['password']);
$con_password=md5($password);
$sql1 = mysql_query("SELECT * FROM ww_users WHERE email = '$email'");
   if(mysql_num_rows($sql1)>0)
   {
   $msg2= "Registered Email Already Exists";
   }
   else
   {
$user_add_query = mysql_query("INSERT INTO `ww_users` (`name`, `email`, `password`, `confirm_password`, `mobile`, `city`, `newsletters`, `terms`, `activation_link`,`profile_image`, `posted_date`) VALUES ('$name', '$email', '$password', '$$con_password', '$mobile', '$city', '', '', '$activation_link','', NOW())");

if($user_add_query)
{
 $msg = "Your password link sent to your e-mail address.";
 
$to =$email;
$from = 'Qikpikur Deals';
$subject = "Activation Email From Qikpikur Deals";
$message = "<html>
<head>
<title>Qikpikur Deals</title>
</head>
<body>
<div style='max-width:530px; min-width:280px; overflow:hidden;'>
	<div style='background:rgba(241, 87, 87, 0.59); padding:20px; overflow:hidden; text-align:center; font-family:Arial, Helvetica, sans-serif; border-bottom:2px solid #c1c1c1;'>
    <img src='http://qikpikur.com/deals/img/logo.png' height='67' width='286' border='0' />
    <div style='background:#ffffff; margin:15px 0px 0px 0px; padding:25px 30px;'>  
	    	<span style='font-size:22px; color:#000000; font-weight:bold; line-height:30px;'>Hi ".$name.",Thanks for signing up!</span>
        <span style='display:block; line-height:24px; font-size:14px; color:#6d6d6d;'>Your account has been created,you can login with your credentials after you have activated by clicking the button below....!!</span>
		 <a href='http://qikpikur.com/deals/confirm.php?passkey=$activation_link' style='color:#ffffff; text-decoration:none; font-weight:bold; font-size:22px;'>
        <div style='display:block; background:#cc0000; padding:10px 0px; width:100%; margin:10px 0px;'>Click Here to Activate</div>
        </a>
    </div>
    </div>
    <div style='overflow:hidden; padding:10px 0px; text-align:center; font-size:12px; color:#000000; font-family:Arial, Helvetica, sans-serif; line-height:22px;'>
    All Rights Reserved © 2017 Digital Stotra
    <small style='color:#999999; display:block;'>Powered by <a href='http://qikpikur.com/' style='color:#999999; text-decoration:none;'>Digital Stotra</a></small>
	</div>
</div>
</body>
</html>
";
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: <deals@qikpikur.com>' . "\r\n";
$sentmail = mail($to,$subject,$message,$headers);
}
}
}
//Signup Ends
if(isset($_POST['signin_submit']))
 {
  $email = trim($_POST['email']);
  $password = md5($_POST['password']);
   
  $query = "SELECT * FROM ww_users WHERE email='$email' AND password='$password'  AND activation_link ='Active'";
  $result = mysql_query($query)or die(mysql_error());
  $num_row = mysql_num_rows($result);
  $row=mysql_fetch_array($result);
  if( $num_row >0 ){
   $_SESSION['user_name']=$row['name'];
  $_SESSION['user_id']= $row['user_id'];
    echo "<script>window.location='my-account.php';</script>";
	$_SESSION['start'] = time();
 
 // taking now logged in time
 $_SESSION['expire'] = $_SESSION['start'] + (5 * 60) ; 
  }
  else
         {
 $msg2='Invalid Username Or Password';
  }
 }



?>
<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
   <title><?php echo $page_details_qry['page_metatitle']; ?></title>	
	<meta name="description" content="<?php echo $page_details_qry['page_metadescription']; ?>">
	<meta name="keywords" content="<?php echo $page_details_qry['page_metakeywords']; ?>">	
	<link rel="shortcut icon" href="admin/uploads/<?php echo $site_details_qry['page_image']; ?>" />
   

    <!-- favicon
		============================================ -->
 

    <!-- Google Fonts
		============================================ -->
    <link href='../../../../https@fonts.googleapis.com/css@family=Lato_3A400,700,300' rel='stylesheet' type='text/css'>

    <!-- Bootstrap CSS
		============================================ -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- icon-7-stroke
		============================================ -->
    <link rel="stylesheet" href="css/pe-icon-7-stroke.css">
    <!-- Font Awesome CSS
		============================================ -->
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <!-- owl.carousel CSS
		============================================ -->
    <link rel="stylesheet" href="css/owl.carousel.css">    
    <!-- nivo slider CSS
    ============================================ -->
    <link rel="stylesheet" href="custom-slider/css/nivo-slider.css" type="text/css" />
    <link rel="stylesheet" href="custom-slider/css/preview.css" type="text/css" media="screen" />
    <!-- meanmenu CSS
		============================================ -->
    <link rel="stylesheet" href="css/meanmenu.min.css">
    <!-- animate CSS
		============================================ -->
    <link rel="stylesheet" href="css/animate.css">
    <!-- style CSS
		============================================ -->
    <link rel="stylesheet" href="style.css">
    <!-- responsive CSS
		============================================ -->
    <link rel="stylesheet" href="css/responsive.css">
    
     <!-- Arcticmodal CSS
		============================================ -->
    <link rel="stylesheet" href="js/arcticmodal/jquery.arcticmodal.css">
    
    <!-- modernizr JS
		============================================ -->    
    <script src="js/vendor/modernizr-2.8.3.min.js"></script>
</head>

<body>
    <!--[if lt IE 8]>
            <p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="../../../../browsehappy.com/default.htm">upgrade your browser</a> to improve your experience.</p>
        <![endif]-->

    <!-- Add your site or application content here -->
    <div class="home-1-waraper">
        <!--header area start-->
     <?php include('includes/header.php'); ?>

        <!--  breadcumb-area start -->
        <div class="breadcumb-area">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <ol class="breadcrumb">
                            <li class="home"><a href="index.php" title="Go to Home Page">Home</a></li>
                            <li class="active">Login</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!--  breadcumb-area end -->

		<!-- Account Area Start -->
        <div class="account-area pb60">
        <div class="container">
            <div class="row">
                <div class="col-sm-6 col-xs-12">
				<?php if(isset($_POST['signin_submit'])) { ?>	<?php if(@$msg){?>
		<div style="color:#009900; text-align:center; font-size: 14px; padding:4px 0px;"><?php echo $msg; ?></div>
		<?php }?>
		<?php if(@$msg2){?>
		<div style="color:#FF0000; text-align:center;  font-size: 14px; padding:4px 0px;"><?php echo $msg2; ?></div>
		<?php }?>
                        <?php  } ?>  
                    <form action="" method="post">
                  <div class="login-reg">
                    <h3>Login</h3>
                    <div class="form-group">
                        <label class="control-label">E-Mail</label>
                        <input type="text" class="form-control" placeholder="E-Mail" value="" name="email" required>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Password</label>
                        <input type="password" class="form-control" placeholder="Password" value="" name="password" required>
                    </div>
                  
                    </div>
                    <div class="frm-action">
                    <div class="buttons common-btn chec">
                        <input type="submit" name="signin_submit" class="btn btn-primary" data-loading-text="Loading..." value="Login">
                    </div>
                     <span>
                         <input class="remr" type="checkbox"> Remember me 
                     </span>
                    <a href="#" class="forgotten">Forgot Password?</a>
                        </div>
                    </form>
                </div>
                <div class="col-sm-6 col-xs-12 lr2">
				<?php if(isset($_POST['register_submit'])) { ?>	<?php if(@$msg){?>
		<div style="color:#009900; text-align:center; font-size: 14px; padding:4px 0px;"><?php echo $msg; ?></div>
		<?php }?>
		<?php if(@$msg2){?>
		<div style="color:#FF0000; text-align:center;  font-size: 14px; padding:4px 0px;"><?php echo $msg2; ?></div>
		<?php }?>
                        <?php  } ?>  
                    <form action="" method="post">
                  <div class="login-reg">
                    <h3>Register</h3>
                    <div class="form-group">
                        <label class="control-label">Full Name</label>
                        <input type="text" class="form-control" placeholder="Name" value="" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="control-label">E-Mail</label>
                        <input type="email" class="form-control" placeholder="E-Mail" value="" name="email" required>
                    </div>
					<div class="form-group">
                        <label class="control-label">Mobile</label>
                        <input type="text" class="form-control" placeholder="Mobile" value="" name="mobile" maxlength="10" required>
                    </div>
					<div class="form-group">
                        <label class="control-label">City</label>
                      <select required class="form-control" name="city">
					  <option value="">Please Select</option>
					   <?php
  $select_user_city=mysql_query("select cat_id,name from ww_city");
  while($city_user_row=mysql_fetch_assoc($select_user_city))
  {
  ?><option value="<?php echo $city_user_row['cat_id']; ?>"><?php echo $city_user_row['name']; ?></option> <?php
  }
 ?>
					  
					  </select>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Password</label>
                        <input type="password" class="form-control" placeholder="Password" value="" name="password" required>
                    </div>
					
                    
					<div class="form-group">
                           
                                
                              <label class="control-label"><input type="checkbox" value="1" name="OFFER_DISCOUNT" required="" id="accept">
                               I Accept <a href="#" target="_blank">Terms And Conditions</a>
                           </label>
                        
                          
                        </div></div>
                    <div class="frm-action">
                    <div class="buttons common-btn chec">
                        <input type="submit" name="register_submit" class="btn btn-primary" data-loading-text="Loading..." value="Register">
                    </div>
                    </div> 
                    </form>                      
                </div>                       
            </div>
        </div>
    </div>
		<!-- Account Area End -->

       
        <?php include('includes/footer.php'); ?>
</body>

</html>