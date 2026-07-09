<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="ErrorPage.aspx.cs" Inherits="kiosk.ErrorPage" %>

<!DOCTYPE html>

<html xmlns="http://www.w3.org/1999/xhtml">
<head runat="server">
    <title></title>
</head>
<body>

    <div style="width:100%;text-align:center;margin-top:50px;">

        <h2>Sunucuya Erişilemiyor</h2>
        <p>Tekrar bağlantı deneniyor, lütfen bekleyiniz.</p>

    </div>
        
</body>
</html>

<script type="text/javascript">

    window.setTimeout(function () {
        window.location.href = "default.aspx";
    }, 5000);

</script>