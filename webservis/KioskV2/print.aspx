<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="print.aspx.cs" Inherits="kiosk.print" %>

<!DOCTYPE html>

<html>
<head>

    <title>Kiosk Bilgi Fişi</title>

    <style>
        table {
            border-collapse: collapse;
            font-family: Consolas;
            font-size: 14px;
        }

        td, th {
            /*border: 1px solid orange;*/
            padding: 4px;
        }
    </style>

</head>

<body onload="javascript:load();" style="margin:0px;width:6cm;text-align:center;">

    <script language="VBScript">
     <!--    
     SUB window_onload()    
         OLECMDID_PRINT = 6
         OLECMDEXECOPT_DONTPROMPTUSER = 2
         OLECMDEXECOPT_PROMPTUSER = 1
         call WB.ExecWB(OLECMDID_PRINT, OLECMDEXECOPT_DONTPROMPTUSER,1)
        
         End Sub
         document.write "<object ID='WB' WIDTH=0 HEIGHT=0 CLASSID='CLSID:8856F961-340A-11D0-A96B-00C04FD705A2'></object>"
     -->
    </script>

    <script type="text/javascript">

        function tarihGetir() {
            var currentdate = new Date();
            var datetime = currentdate.getDate() + "/"
                            + (currentdate.getMonth() + 1) + "/"
                            + currentdate.getFullYear();
            return datetime;
        }

        function saatGetir() {
            var currentdate = new Date();
            var datetime = currentdate.getHours() + ":" + currentdate.getMinutes();
            return datetime;
        }

        function load() {
            document.getElementById("tarih").innerHTML = tarihGetir();
            document.getElementById("saat").innerHTML = saatGetir();
            document.getElementById("aboneno").innerHTML = queryStringOku('aboneno');
            document.getElementById("miktar").innerHTML = queryStringOku('miktar');
            document.getElementById("beladi").innerHTML = queryStringOku('beladi');
                        
            window.setTimeout(function () {
                window.close();
            }, 10);            
        }

        function queryStringOku(name, url) {
            if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, "\\$&");
            var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, " "));
        }

    </script>

    <table style="width:6cm;text-align:left;">
        <thead>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <img src="images/belediyeLogo.png" style="width:1.7cm" />
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align:center;padding-top:7px;padding-bottom:7px;"><b><span id="beladi"></span></b></td>
            </tr>            
        </thead>
        <tr>
            <td><span id="tarih"></span></td>
            <td style="text-align:right"><span id="saat"></span></td>
        </tr>
        <tr>
            <td>ABONE NO</td>
            <td style="text-align:right"><span id="aboneno"></span></td>
        </tr>
        <tr>
            <td>YÜKLENEN MİKTAR</td>
            <td style="text-align:right"><span id="miktar"></span></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center;padding-top:10px;">BİLGİ FİŞİDİR</td>
        </tr>
    </table>

</body>
</html>

