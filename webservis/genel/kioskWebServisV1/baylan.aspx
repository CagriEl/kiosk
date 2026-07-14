<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="baylan.aspx.cs" Inherits="Kiosk.baylan" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<script  type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
<script type="text/javascript" src="js/jquery-migrate-1.2.1.min.js"></script>
<script id="degerOkuScript" type="text/javascript" src="../../global/degerOku.js"></script>
<script language="javascript">
    var flagJS = 1;
    var kredi = 0;
    var vs_LogSql = "";
    function formload() {

        /*var obj = document.getElementById("TestActivex");
        obj.KartOku();
        alert(obj.KartBilgisi.Kredi + " - " + obj.KartBilgisi.YedekKredi)*/
    
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

    }

    
    
    function btnKartOku_Click() {
        //suKartliLog('Kiosk Okumaya Girdi!');
        var objKontrol = document.getElementById("TestActivex");
        var islemSonuc = objKontrol.KartSeriNoOku();
        if (islemSonuc) {            
            document.getElementById("txtKartId").value = objKontrol.KartBilgisi.KartSeriNo;
            
            //form1.submit();
        } else {alert('Kart Okunamadı.'); }
    }

    function btnGiris_Click() {
        document.getElementById("txtKartId").value = "";
        //form1.submit();
    }

 
    function btnYukle_Click() {
        var flag =0;

        document.getElementById("btnYukle").style.display = 'none';
        document.getElementById("btnYukle").disabled = true;
        var obj = document.getElementById("TestActivex");
        var islemSonuc = obj.KartSeriNoOku();
        var Kredi = document.getElementById('txtYuklenecek').value;
        var YedekKredi = '0';
        //suKartliLog('Kiosk Yüklemesi');
        document.getElementById("txtKartId").value = obj.KartBilgisi.KartSeriNo;

        if (document.getElementById("txtKartIDS").value != obj.KartBilgisi.KartSeriNo) {
            alert('Kart değiştirilmiş.');
            return false;
        }
        
        if (islemSonuc) {
            document.getElementById("txtKartId").value = obj.KartBilgisi.KartSeriNo;
        } else {
        alert('Kart Okunamadı.'); 
        }
        obj.SayacModeliId = document.getElementById("txtKartTipi").value;
                
        document.getElementById("txtYukle").value = "1";
        document.getElementById("txtAboneNoC").value = document.getElementById("txtAboneNo").value;
        document.getElementById("txtDonemC").value = document.getElementById("txtDonem").value;
        document.getElementById("txtGensicilnoC").value = document.getElementById("txtGensicilno").value;
        document.getElementById("txtIlkEndeksC").value = document.getElementById("txtIlkEndeks").value;
        document.getElementById("txtYuklenecekC").value = document.getElementById("txtYuklenecek").value;
        document.getElementById("txtAtikSuMikC").value = document.getElementById("txtAtikSuMik").value;

        obj.KartBilgisi.AboneNo = document.getElementById("txtAboneNo").value;
        obj.KartBilgisi.SayacNo = document.getElementById('txtCihazNo').value;

        obj.KartBilgisi.Fiyat1 = document.getElementById("txtKatSayi1").value;
        obj.KartBilgisi.Fiyat2 = document.getElementById("txtKatSayi2").value;
        obj.KartBilgisi.Fiyat3 = document.getElementById("txtKatSayi3").value;
        obj.KartBilgisi.Fiyat4 = document.getElementById("txtKatSayi3").value;
        obj.KartBilgisi.Fiyat5 = document.getElementById("txtKatSayi3").value;
        obj.KartBilgisi.Fiyat6 = document.getElementById("txtKatSayi3").value;
        obj.KartBilgisi.Fiyat7 = document.getElementById("txtKatSayi3").value;

        obj.KartBilgisi.KademeUstSinir1 = document.getElementById("txtKademe1").value;
        obj.KartBilgisi.KademeUstSinir2 = document.getElementById("txtKademe2").value;
        obj.KartBilgisi.KademeUstSinir3 = document.getElementById("txtKademe2").value;
        obj.KartBilgisi.KademeUstSinir4 = document.getElementById("txtKademe2").value;
        obj.KartBilgisi.KademeUstSinir5 = document.getElementById("txtKademe2").value;
        obj.KartBilgisi.KademeUstSinir6 = document.getElementById("txtKademe2").value;
        
        obj.KartBilgisi.KritikKredi = '0';
        obj.KartBilgisi.DonemGunSayisi = '30';
        
        obj.KartBilgisi.Kredi = document.getElementById('txtYuklenecek').value;
        obj.KartBilgisi.YedekKredi = '0';
        
        /*emrahsenturk (02/02/2016) - Çift yükleme yapılmaması için kontrol*/
        strSQLKontrol = "select isnull((select aboneno";
        strSQLKontrol = strSQLKontrol + " from suabone where kartId='" + document.getElementById("txtKartId").value + "'";
        strSQLKontrol = strSQLKontrol + " and exists(select * from gtttah where modulno=24 and exists (select * from subeyan where tahnot='Kartlı Abone Avans Kontör'";
        strSQLKontrol = strSQLKontrol + " and subeyan.aboneno=suabone.aboneno and subeyan.gensicilno=suabone.gensicilno and recid=beyan_id) and bakiye>0)),0) aboneno";
        var dahaOnceVarMi = degerOku(strSQLKontrol);
        if (dahaOnceVarMi != 0) {
            alert("Zaten avans kontör yüklenmiş. Yükleme yapılamaz!")
            return false;
        }
        /**/
        else {
            flag = 1;
            if (obj.KrediYukle(Kredi, YedekKredi)) {

                vtYaz();
                //alert("Kontor yükleme işlemi başarılı.");
                //form1.submit();
            }
            else {
                alert('Kontör yazımı gerçekleştirilemedi. (Hata Kodu:' + obj.SonHataMesaji + ')');
            }
        }
        
        if (flag == 1) {
            suKartliLog('Kiosk Yüklemesi');
        }
              
    }

    function suKartliLog(aciklama) {
        
       /* if (document.getElementById("txtYuklenecek").value = '')
        {
            document.getElementById("txtYuklenecek").value = 0;
        }
        if (document.getElementById("txtaboneno").value = '')
        {
            document.getElementById("txtaboneno").value = 0;
        }*/
        //alert(document.getElementById("txtAboneNo").value);
        $.ajax({
            type: "POST",
            url: "web.asmx/suKartliLog",
            data: { txtAboneNoC: document.getElementById("txtAboneNo").value, txtYuklenecekC: document.getElementById("txtYuklenecek").value, txtAciklamaC: aciklama },
            success: function (args2) {
                
                if (args2.Value == "ok") {
                    setTimeout(function () {
                        window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                    }, 7000);
                }
                else {
                    alert("İşlem Başarısız Yöneticiye Başvurun");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }
            },
            error: function (o) {
                alert("Webservise bağlanılamadı.Yöneticiye başvurunuz.");
                console.log(o);
            }
        });
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
                        window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx'; //will redirect to your blog page (an ex: blog.html)
                    }, 7000);
                }
                else {
                    //console.log(args.Value);
                    alert("İşlem Başarısız Yöneticiye Başvurun");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }

            },
            error: function (o) {
                alert("Webservise bağlanılamadı.Yöneticiye başvurunuz.");
                console.log(o);

            }
        });
    }

</script>
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
    <title>Baylan</title>
</head>
<body onload="formload()">
    <form id="form2" runat="server">
        <tr style="display:none">
            <td>
                <asp:TextBox ID="txtKartIDS"    runat="server" style="display:none"></asp:TextBox>
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
    
    <tr style="display:none">
        <td>
            <input id="txtKartId" name="txtKartId" />
            <input id="txtYukle" name="txtYukle" />
            <input id="txtAboneNoC" name="txtAboneNoC" />
            <input id="txtDonemC" name="txtDonemC" />
            <input id="txtGensicilnoC" name="txtGensicilnoC" />
            <input id="txtIlkEndeksC" name="txtIlkEndeksC" />
            <input id="txtYuklenecekC" name="txtYuklenecekC" />
            <input id="txtAtikSuMikC" name="txtAtikSuMikC" />
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
        <button id="btnKartOku" type=submit style="width:600px;Height:125px;Font-Size:XX-Large" onclick="btnKartOku_Click()">
        Kart Oku</button>
        </td>
    </tr>

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
