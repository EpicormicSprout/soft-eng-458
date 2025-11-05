<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<!--
    Last Modified: 11/3/2025
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
        <!-- Currently the textarea for the thesisAbstract input. I'm not sure how to adjust
             the size of the area by the size of the window. -->
        <textarea name="thesisAbstract" id="thesisAbstract" placeholder="Input Thesis Abstract..."
                  rows="15" cols="60" required="required">
        </textarea><br><br>
        <input class="predictButton" type="submit" value="Run Prediction">
      </fieldset>
    </form>

    <!-- SDG Metrics for Total Database and maybe Legend tab. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <!-- TBH I have no clue how to implement that pie chart or the legend. -->
        <legend> SDG Metrics </legend>
      </fieldset>
    </form>
    
    <!-- Model tagging area. Will have buttons and widgets for edits and tags. -->
    <!-- Okay! My assumption is that this will only appear after the postback of the 
         textarea. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> Model Tagging </legend>
          <!-- Start of php. This will create as many labels as needed based on the how many tags the bot gives the abstact -->
          <!-- This is a template of how the label will look. Obviously the value and name is temporary. It will be variables
               based on the bot's output -->
          <label for="sdgConfidence"> SDG Placeholder:
            <progress id="sdgConfidence" value="60" max="100"> 60 % </progress> 60 %
          </label> <br> <br>
          
          <!-- These buttons will be given different effects -->
          <button id="approve" type="button"> Approve </button>
          <button id="edit" type="button"> Edit </button>
          <button id="save" type="button"> Save to Database </button>
      </fieldset>
    </form>

    <!-- Maybe the popout window. IDK I haven't figured it out. -->
    <!-- The general idea for this one is to hide this form until the edit button is pressed.
         Not currently sure how to do that. -->
    <form action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES) ?>" method="post">
      <fieldset>
        <legend> Popout Box </legend>
      </fieldset>
    </form>
  </div>

  <footer>
    <p> Project by Nick Michel, Courtney Rowe, Hayden Weber, Marceline Vasquez Rios </p>
  </footer>
</body>
