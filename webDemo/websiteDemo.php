<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    Last Modified: 10/26/2025
        By: Nick Michel

    you can run this using the URL: https://nrs-projects.humboldt.edu/~nam116/CS458/webDemo/websiteDemo.php
                                    Just add your own URL onto this. 
-->

<!-- ***** There is a lot to be added to this. *****
      Firstly, there is no PHP yet. 
      Secondly, there has not been any work in SQL yet.
      Finally, there will be a lot more added to the base html.

    ***** Documentation Please *****

      Make sure to DOCUMENT any changes made to the files in gitHub.


    *****                      *****
-->
    
<head>
    <title> Website Demo </title>
    <meta charset="utf-8" />

      <?php
        ini_set('display_errors', 1);
        error_reporting(E_ALL); 
        require_once("hum_conn_no_login.php"); 
    ?>

    <link href="demo.css" type="text/css" rel="stylesheet" />
</head>

 
<body>
  <header>
    <!-- Displays the background and logo. -->
    <a class="logo" href="#"> <img id="banner_logo" alt="Cal Poly Humboldt Digital Commons" 
    src="CalPolyHumboldt_PrimaryLogos/PNG/CalPolyHumboldt_primary_dual-green.png"> </a>
  </header>
  <!-- This div is used by the CSS file to display the forms in a grid -->
  <div class="formContainer">
    <!-- This is the form for the Thesis Abstract textbox -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> Thesis Input Field </legend>
      </fieldset>
    </form>

    <!-- SDG Metrics for Total Database and maybe Legend tab. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> SDG Metrics </legend>
      </fieldset>
    </form>
    
    <!-- Model tagging area. Will have buttons and widgets for edits and tags. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> Model Tagging </legend>
      </fieldset>
    </form>

    <!-- Maybe the popout window. IDK I haven't figured it out. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> Popout Box </legend>
      </fieldset>
    </form>
  </div>

  <footer>
    <p class="whiteFont"> Project by Nick Michel, Courtney Rowe, Hayden Weber, Marceline Vasquez Rios </p>
  </footer>
</body>
