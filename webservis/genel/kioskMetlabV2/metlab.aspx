<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="metlab.aspx.cs" Inherits="Kiosk.baylan" EnableEventValidation="false" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
    <title>Metlab</title>
    <script  type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
    <script type="text/javascript" src="js/jquery-migrate-1.2.1.min.js"></script>
    <script id="degerOkuScript" type="text/javascript" src="../../global/degerOku.js"></script>

    <script type="text/javascript">

    function formload() {
        document.getElementById("txtYukle").value = "";
        if (document.getElementById("txtBakiye").value == 0) {
            document.getElementById("btnYukle").style.display = 'block'
            document.getElementById("btnKartOku").style.display = 'none'
        }
        else {
            document.getElementById("btnYukle").style.display = 'none'
            document.getElementById("btnKartOku").style.display = 'block'
        }
        if (document.getElementById("txtError").value != "") {
            alert(document.getElementById("txtError").value)
            document.getElementById("btnYukle").style.display = 'none'
        }
        document.getElementById("txtKontrol").value = "1";
    }

    var objRequest;
    var kartTip, buytimes;

    function btnKartOku_Click() {
        var obj = new ActiveXObject('MetLab.EKSAPI');
        obj.KartOku();
        kartTip = obj.KartBilgisiV2.KartTipi;
        if (kartTip == 1 || kartTip == 2) {
            document.getElementById("txtCihazNoC").value = obj.KartBilgisiV2.SayacNo;
        }
        else {
            alert('Kart Okunamadı.');
        }
    }

    function btnGiris_Click() {
        document.getElementById("txtCihazNo").value = "";
    }

    function btnYukle_Click() {
        document.getElementById("btnYukle").style.display = 'none';
        var obj = new ActiveXObject('MetLab.EKSAPI');
        obj.KartOku();
        document.getElementById("txtCihazNoC").value = obj.KartBilgisiV2.SayacNo;
        if (document.getElementById("txtCihazNo").value != obj.KartBilgisiV2.SayacNo) {
            alert('Kart bulunamadı yada kart değiştirilmiş!');
            document.getElementById("txtKontrol").value = "0";
            return false;
        }

        //var islemSonuc = obj.KartOku();
        var Kredi = document.getElementById('txtYuklenecek').value;
        var YedekKredi = '0';

        if (document.getElementById("txtYuklenecek").value == '') {
            alert("Yüklenecek miktar girilmemiş.");
            return false;
        }

        if (kartTip == 1) {
            if (obj.KartBilgisiV2.SatisSayisi + 1 > 99)
                obj.KartBilgisiV2.SatisSayisi = 0;
            else
                obj.KartBilgisiV2.SatisSayisi = obj.KartBilgisiV2.SatisSayisi + 1;
        }
        else {
            obj.KartBilgisiV2.SatisSayisi = obj.KartBilgisiV2.SatisSayisi;
        }

        obj.KartBilgisiV2.KritikKredi = 5;
        obj.KartBilgisiV2.SayacNo = document.getElementById("txtCihazNo").value;

        obj.KartBilgisiV2.BaslangicDakika = 0;
        obj.KartBilgisiV2.BaslangicSaat = 8;
        obj.KartBilgisiV2.BitisDakika = 0;
        obj.KartBilgisiV2.BitisSaat = 17;

        if (document.getElementById("txtCihazNo").value != obj.KartBilgisiV2.SayacNo) {
            alert('Kart değiştirilmiş.');
            return false;
        }

        document.getElementById("txtYukle").value = "1";
        document.getElementById("txtAboneNoC").value = document.getElementById("txtAboneNo").value;
        document.getElementById("txtDonemC").value = document.getElementById("txtDonem").value;
        document.getElementById("txtGensicilnoC").value = document.getElementById("txtGensicilno").value;
        document.getElementById("txtIlkEndeksC").value = document.getElementById("txtIlkEndeks").value;
        document.getElementById("txtYuklenecekC").value = document.getElementById("txtYuklenecek").value;
        document.getElementById("txtAtikSuMikC").value = document.getElementById("txtAtikSuMik").value;

        /*emrahsenturk (02/02/2016) - Çift yükleme yapılmaması için kontrol*/
        strSQLKontrol = "select aboneno";
        strSQLKontrol = strSQLKontrol + " from suabone where sayacno='" + document.getElementById("txtCihazNo").value + "'";
        strSQLKontrol = strSQLKontrol + " and exists(select * from gtttah where modulno=24 and exists (select * from subeyan where tahnot='Kartlı Abone Avans Kontör'";
        strSQLKontrol = strSQLKontrol + " and subeyan.aboneno=suabone.aboneno and subeyan.gensicilno=suabone.gensicilno and recid=beyan_id) and bakiye>0)";
        var dahaOnceVarMi = degerOku(strSQLKontrol);
        if (dahaOnceVarMi != "") {
            alert("Zaten avans kontör yüklenmiş. Yükleme yapılamaz!")
            return false;
        }
        /**/

        if (obj.KrediYukle(document.getElementById("txtYuklenecekC").value, 0)) {
            vtYaz();
            //alert("Kontor yükleme işlemi başarılı.");
        }
        else {
            alert('Kontör yazımı gerçekleştirilemedi. (Hata Kodu:' + obj.SonHataMesaji + ')');
        }
    }

    function vtYaz() {
        $.ajax({
            type: "POST",
            url: "web.asmx/dbYaz",
            data: { txtIlkEndeksC: document.getElementById("txtIlkEndeks").value, txtYuklenecekC: document.getElementById("txtYuklenecek").value, txtDonemC: document.getElementById("txtDonem").value, txtAboneNoC: document.getElementById("txtAboneNo").value, txtGensicilnoC: document.getElementById("txtGensicilno").value, txtAtikSuMikC: document.getElementById("txtAtikSuMik").value },
            success: function (args) {
                if (args.Value == "ok") {
                    //alert("Avans Kredi Yükleme İşlemi Başarılı Bir Şekilde Gerçekleşmiştir.");
                    //console.log(args.Value);
                    document.getElementById("lblSMesaj").innerHTML = "Yüklenen avans kredi miktarı " + document.getElementById("txtYuklenecek").value + " tondur.</br> 7 Gün içerisinde " + document.getElementById("txtYuklenecek").value + " ton su tutarını ödemediğiniz takdirde gecikme zammı uygulanacaktır.</br> İyi günler dileriz.";
                    setTimeout(function () {
                        window.location.href = '/Belsis-Net/genel/kioskMetlabv2/metlab.aspx'; //will redirect to your blog page (an ex: blog.html)
                    }, 7000);
                }
                else {
                    //console.log(args.Value);
                    alert("İşlem Başarısız Yöneticiye Başvurun");
                    window.location.href = '/Belsis-Net/genel/kioskMetlabv2/metlab.aspx';
                }
               
            },
            error: function (o) {
                alert("İşlem Başarısız Yöneticiye Başvurun,1");
                console.log(o);

            }
        });
    }
   


    function queryStringOku(name, url) {
        if (!url) {
            url = window.location.href;
        }
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, " "));
    }


    </script>
</head>
<body onload="formload()">
    <form id="form2" runat="server">
        <tr style="display:none">
            <td>
                <asp:TextBox ID="txtKartIDS"     runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtBakiye"     runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtAboneNo"    runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtDonem"      runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtGensicilno" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtIlkEndeks"  runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtYuklenecek" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtAtikSuMik"  runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtError"      runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKartTipi"   runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi1"   runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi2"   runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi3"   runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKademe1"    runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKademe2"    runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtTipAcik"    runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtCihazNo"    runat="server" style="display:none"></asp:TextBox>
                
           </td>
       </tr>
    </form>
    <form id="form1">
    <center>
    
    <table style="border-collapse:collapse;border:0px;font-family:Tahoma;font-size:11px;background-color:White">
    
    <tr>
        <td style="display:none"> 
            <input id="txtKartId" name="txtKartId" />
            <input id="txtYukle" name="txtYukle" />
            <input id="txtAboneNoC" name="txtAboneNoC" />
            <input id="txtCihazNoC" name="txtCihazNoC" />
            <input id="txtDonemC" name="txtDonemC" />
            <input id="txtGensicilnoC" name="txtGensicilnoC" />
            <input id="txtIlkEndeksC" name="txtIlkEndeksC" />
            <input id="txtYuklenecekC" name="txtYuklenecekC" />
            <input id="txtAtikSuMikC" name="txtAtikSuMikC" />
            <input id="txtKontrol" name="txtKontrol" />
        </td>
    </tr>
    
    <tr>
        <td colspan="2" style="font-size:xx-large;width:600px; height:175px;background-color:steelblue;color:White;text-align:center;font-weight:bold">
        Avans Kredi Yükleme</td>
    </tr>
    
    <tr><td style="height:10px"></td></tr>
    
    <tr>
        <td colspan="2" align="center" style="font-size:xx-large;width:600px; height:125px;border-top:0px">
         <asp:Label ID="lblSMesaj" runat="server" ForeColor="maroon"/></td>
         
    </tr>  
    
    <tr>
        <td colspan="2" style="border-top:0px;text-align:center">
        <button type=submit id="btnKartOku" style="width:600px;Height:125px;Font-Size:XX-Large" onclick="btnKartOku_Click()">
        Kart Oku</button>
        </td>
    </tr>
        
      <%--   <tr>
        <td colspan="2" style="border-top:0px;text-align:center">
         <button id="btnGetTime" type="button" value="Show Current Time" onclick = "ShowCurrentTime()">  sss</button>
        </td>
          
    </tr>--%>
    <tr>
        <td colspan="2" style="border-top:0px;text-align:center">
            <button id="btnYukle" style="width:600px;Height:125px;Font-Size:XX-Large;display:none" onclick="btnYukle_Click()">
            Avans Yükle</button>
        </td>
    </tr>

 
     <tr>
        <td colspan="2" style="border-top:0px;text-align:center">
            <button id="btnGiris" type=submit style="width:600px;Height:125px;Font-Size:XX-Large;color:Red" onclick="btnGiris_Click()">
            İptal</button>
        </td>
    </tr>
       
    <tr>
        <td colspan="2" style="font-size:large;width:600px; height:100px;color:Red;text-align:center;font-weight:bold">
            İşleminiz bitene kadar kartı yerinden oynatmayınız.
        </td>
    </tr>

    </table>
    </center> 

    </form>
    <object name='secondobj' style='display:none' id='TestActivex' classid='CLSID:E7CFB476-B06D-30FA-BA42-FBD9485BB296'></object>

</body>
</html>
