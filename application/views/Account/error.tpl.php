<?php if (!defined('FARI')) die(); include('application/views/head.tpl.php'); ?>

<body>
    <div id="modal">
        <img src="<?php url('/public/images/clubhouse-small-header-logo.png'); ?>" alt="clubhouse logo"/>
        <h1>Sorry, there was a problem creating your account</h1>
        <p>Please visit the account invitation page again or write the person who invited you and ask them to send the
            invitation again.<br />We're sorry for the trouble.</p>
        <hr>
        <p>
            <a href="javascript:history.go(-1)">Go back a page</a>
        </p>
    </div>
</body>
</html>