<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="baylan.aspx.cs" Inherits="Kiosk.baylan" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
 <script  type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
 <script type="text/javascript" src="js/jquery-migrate-1.2.1.min.js"></script>
<script id="degerOkuScript" type="text/javascript" src="../../global/degerOku.js"></script>
<script language="javascript">

    var kartYazData_ak11 = { 
        AboneNo: "0", SayacNo: "0", KritikBakiye: "0", Tarife1: "0", Tarife2: "0", Tarife3: "0", Tarife4: "0",
        Kademe1: "0", Kademe2: "0", Kademe3: "0", Donem: "0",  YuklenecekKredi: "0", YedekBakiye: "0",
        AvansKullanimiAktiflik: "0", KarttakiBakiyeyiSifirla: false, YedekBakiyeSifirla: false
    };
        
    var kartYazData_ak21 = {
        AboneNo: "0", SayacNo: "0", KritikBakiye: "0", Tarife1: "0", Tarife2: "0", Tarife3: "0", Tarife4: "0", 
        Kademe1: "0", Kademe2: "0", Kademe3: "0", Donem: "0", YuklenecekKredi: "0", AvansKullanimiAktiflik: "0", 
        KarttakiBakiyeyiSifirla: false, YedekBakiyeSifirla: false, KesintisizSuBaslangicTarihi: "",
        KesintisizSuBitisTarihi: "", YedekKredi: "0", VezneKodu: "0", AboneTipi: "", KartTipi: "Yeni"
    };
        
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
            alert(document.getElementById("txtError").value);
            document.getElementById("btnYukle").style.display = 'none'
        }
    }
    
    function btnKartOku_Click() {
        var objKontrol = document.getElementById("TestActivex11");
        objKontrol.KartOkuyucuPortu();

        var islemSonuc = objKontrol.KartIdOku();
        //kartTipi = degerOku('select kartTipi from suabone where kartId=\'' + islemSonuc.Sonuc + '\'');
       
        if (islemSonuc) {
            document.getElementById("txtKartId").value = islemSonuc.Sonuc;
        } else { alert('Kart Okunamadı.'); }
        
    }

    function btnGiris_Click() {
        document.getElementById("txtKartId").value = "";
    }

    function btnYukle_Click() {
        document.getElementById("btnYukle").style.display = 'none';
        document.getElementById("btnYukle").readonly = 'readonly';
        var objKontrol = document.getElementById("TestActivex11");
        objKontrol.KartOkuyucuPortu();
       
        var islemSonuc = objKontrol.KartIdOku();
        document.getElementById("txtKartId").value = islemSonuc.Sonuc;
        
        document.getElementById("txtYukle").value = "1";
        document.getElementById("txtAboneNoC").value = document.getElementById("txtAboneNo").value;
        document.getElementById("txtDonemC").value = document.getElementById("txtDonem").value;
        document.getElementById("txtGensicilnoC").value = document.getElementById("txtGensicilno").value;
        document.getElementById("txtIlkEndeksC").value = document.getElementById("txtIlkEndeks").value;
        document.getElementById("txtYuklenecekC").value = document.getElementById("txtYuklenecek").value;
        document.getElementById("txtAtikSuMikC").value = document.getElementById("txtAtikSuMik").value;

        strSQLKontrol = "select aboneno";
        strSQLKontrol = strSQLKontrol + " from suabone where kartId='" + document.getElementById("txtKartId").value + "'";
        strSQLKontrol = strSQLKontrol + " and exists(select * from gtttah where modulno=24 and exists (select * from subeyan where tahnot='Kartlı Abone Avans Kontör'";
        strSQLKontrol = strSQLKontrol + " and subeyan.aboneno=suabone.aboneno and subeyan.gensicilno=suabone.gensicilno and recid=beyan_id) and bakiye>0)";
        var dahaOnceVarMi = degerOku(strSQLKontrol);
        if (dahaOnceVarMi != "") {
            alert("Zaten avans kontör yüklenmiş. Yükleme yapılamaz!")
            return false;
        }

        
        if (document.getElementById("txtKartTipi").value == 1) {
            var obj = document.getElementById("TestActivex11");
            try {
                obj.KrediYukle(document.getElementById('txtYuklenecek').value, 0);
                vtYaz();
            }
            catch (err)
            {
                uiBilgiVer('Kontör yazımı gerçekleştirilemedi.');
                document.getElementById("txtYukle").value = "0";
                return false;
            }
        }
        else {
            var obj = document.getElementById("TestActivex21");
            try {
                obj.KrediYukle(document.getElementById('txtYuklenecek').value, 0);
                vtYaz();
            } catch (err) {
                uiBilgiVer('Kontör yazımı gerçekleştirilemedi.');
                document.getElementById("txtYukle").value = "0";
                return false;
            }
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
                        window.location.href = '/Belsis-Net/genel/kioskWebServisV2/baylan.aspx'; //will redirect to your blog page (an ex: blog.html)
                    }, 7000);
                }
                else {
                    //console.log(args.Value);
                    alert("İşlem Başarısız Yöneticiye Başvurun");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV2/baylan.aspx';
                }

            },
            error: function (o) {
                alert("error");
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
    <form id=form2 runat=server>
        <tr style="display:none">
            <td>
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
            <!--
            <input ID="txtKartTipiC"   name="txtKartTipiC"/>
            <input ID="txtKatSayi1C"   name="txtKatSayi1C"/>
            <input ID="txtKatSayi2C"   name="txtKatSayi2C"/>
            <input ID="txtKatSayi3C"   name="txtKatSayi3C"/>
            <input ID="txtKademe1C"    name="txtKademe1C"/>
            <input ID="txtKademe2C"    name="txtKademe2C"/>
            <input ID="txtTipAcikC"    name="txtTipAcikC"/>-->
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
    <object name="firstobj" style='display:none' id='TestActivex11' classid='CLSID:A5073676-15FD-42C5-B14D-F7FDB07A76A9' codebase='BaylanAxSetup.cab#version=1,0,0,0'></object>
    <object name="secondobj" style='display:none' id='TestActivex21' classid='CLSID:99F2036D-4A0A-414D-9BE0-E2293BFA4A03' codebase='BaylanAxSetup.cab#version=1,0,0,0'></object>
    
</body>
</html>
