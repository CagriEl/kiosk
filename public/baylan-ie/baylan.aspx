<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="baylan.aspx.cs" Inherits="Kiosk.baylan" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<script type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
<script type="text/javascript" src="js/jquery-migrate-1.2.1.min.js"></script>
<script id="degerOkuScript" type="text/javascript" src="../../global/degerOku.js"></script>
<script language="javascript">
    var flagJS = 1;
    var kredi = 0;
    var vs_LogSql = "";
    var kioskDonusZamanlayici = null;
    var kioskDonusSayaci = null;
    var KIOSK_ANA_SAYFA = "http://10.0.1.1/kiosk/public";
    var WINDOWS_7 = navigator.userAgent.indexOf("Windows NT 6.1") >= 0;

    function kioskAnaSayfayaDonusBaslat() {
        if (kioskDonusZamanlayici != null) {
            return;
        }

        /* Windows 7'de Chrome/borc ekrani gecisini tamamen kapat.
           Eski davranis: 7 saniye sonra Baylan sayfasini yenile. */
        if (WINDOWS_7) {
            kioskDonusZamanlayici = window.setTimeout(function () {
                window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
            }, 7000);
            return;
        }

        var kalanSaniye = 15;
        var donusMesaji = document.getElementById("kioskDonusMesaji");
        if (donusMesaji) {
            donusMesaji.style.display = "block";
            donusMesaji.innerHTML = kalanSaniye + " saniye sonra borc sorgulama ekranina donulecektir.";
        }

        kioskDonusSayaci = window.setInterval(function () {
            kalanSaniye = kalanSaniye - 1;
            if (donusMesaji && kalanSaniye > 0) {
                donusMesaji.innerHTML = kalanSaniye + " saniye sonra borc sorgulama ekranina donulecektir.";
            }
            if (kalanSaniye <= 0) {
                window.clearInterval(kioskDonusSayaci);
            }
        }, 1000);

        kioskDonusZamanlayici = window.setTimeout(function () {
            chromeKioskaGit();
        }, 15000);
    }

    /* Edge IE -> Chrome kiosk (borc sorgu): her zaman 10.0.1.1/kiosk/public */
    function chromeKioskaGit() {
        if (WINDOWS_7) {
            window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
            return;
        }

        var url = KIOSK_ANA_SAYFA;
        var chromePaths = [
            "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
            "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe"
        ];
        var i;
        var started = false;

        try {
            var shell = new ActiveXObject("WScript.Shell");
            for (i = 0; i < chromePaths.length; i++) {
                try {
                    shell.Run('"' + chromePaths[i] + '" --kiosk "' + url + '"', 1, false);
                    started = true;
                    break;
                } catch (e1) { }
            }
        } catch (e2) { }

        if (!started) {
            window.location.href = url;
        }
    }

    function formload() {
        if (WINDOWS_7) {
            var goBox = document.getElementById("goBox");
            if (goBox) {
                goBox.style.display = "none";
            }
        }

        document.getElementById("txtYukle").value = "";
        if (document.getElementById("txtBakiye").value == 0) {
            document.getElementById("btnYukle").style.display = 'block';
            document.getElementById("btnKartOku").style.display = 'none';
        }
        else {
            document.getElementById("btnYukle").style.display = 'none';
            document.getElementById("btnKartOku").style.display = 'block';
        }
        if (document.getElementById("txtError").value != "") {
            alert(document.getElementById("txtError").value);
            document.getElementById("btnYukle").style.display = 'none';
        }
    }

    function btnKartOku_Click() {
        var objKontrol = document.getElementById("TestActivex");
        var islemSonuc = objKontrol.KartSeriNoOku();
        if (islemSonuc) {
            document.getElementById("txtKartId").value = objKontrol.KartBilgisi.KartSeriNo;
        } else {
            alert('Kart Okunamadi.');
        }
    }

    function btnGiris_Click() {
        document.getElementById("txtKartId").value = "";
        if (WINDOWS_7) {
            window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
            return false;
        }
        chromeKioskaGit();
        return false;
    }

    function btnYukle_Click() {
        var flag = 0;

        document.getElementById("btnYukle").style.display = 'none';
        document.getElementById("btnYukle").disabled = true;
        var obj = document.getElementById("TestActivex");
        var islemSonuc = obj.KartSeriNoOku();
        var Kredi = document.getElementById('txtYuklenecek').value;
        var YedekKredi = '0';
        document.getElementById("txtKartId").value = obj.KartBilgisi.KartSeriNo;

        if (document.getElementById("txtKartIDS").value != obj.KartBilgisi.KartSeriNo) {
            alert('Kart degistirilmis.');
            return false;
        }

        if (islemSonuc) {
            document.getElementById("txtKartId").value = obj.KartBilgisi.KartSeriNo;
        } else {
            alert('Kart Okunamadi.');
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

        /*emrahsenturk (02/02/2016) - Cift yukleme yapilmamasi icin kontrol*/
        strSQLKontrol = "select isnull((select aboneno";
        strSQLKontrol = strSQLKontrol + " from suabone where kartId='" + document.getElementById("txtKartId").value + "'";
        strSQLKontrol = strSQLKontrol + " and exists(select * from gtttah where modulno=24 and exists (select * from subeyan where tahnot='Kartl" + "\u0131" + " Abone Avans Kont" + "\u00f6" + "r'";
        strSQLKontrol = strSQLKontrol + " and subeyan.aboneno=suabone.aboneno and subeyan.gensicilno=suabone.gensicilno and recid=beyan_id) and bakiye>0)),0) aboneno";
        var dahaOnceVarMi = degerOku(strSQLKontrol);
        if (dahaOnceVarMi != 0) {
            alert("Zaten avans kontor yuklenmis. Yukleme yapilamaz!");
            return false;
        }
        else {
            flag = 1;
            if (obj.KrediYukle(Kredi, YedekKredi)) {
                vtYaz();
            }
            else {
                alert('Kontor yazimi gerceklestirilemedi. (Hata Kodu:' + obj.SonHataMesaji + ')');
            }
        }

        if (flag == 1) {
            suKartliLog('Kiosk Yuklemesi');
        }
    }

    function suKartliLog(aciklama) {
        $.ajax({
            type: "POST",
            url: "web.asmx/suKartliLog",
            data: { txtAboneNoC: document.getElementById("txtAboneNo").value, txtYuklenecekC: document.getElementById("txtYuklenecek").value, txtAciklamaC: aciklama },
            success: function (args2) {
                if (args2.Value != "ok") {
                    alert("Islem Basarisiz Yoneticiye Basvurun");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }
            },
            error: function (o) {
                alert("Webservise baglanilamadi.Yoneticiye basvurunuz.");
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
                    document.getElementById("lblSMesaj").innerHTML = "Yuklenen avans kredi miktari " + document.getElementById("txtYuklenecek").value + " tondur.</br> 7 Gun icerisinde " + document.getElementById("txtYuklenecek").value + " ton su tutarini odemediginiz takdirde gecikme zammi uygulanacaktir.</br> Iyi gunler dileriz.";
                    kioskAnaSayfayaDonusBaslat();
                }
                else {
                    alert("Islem Basarisiz Yoneticiye Basvurun");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }
            },
            error: function (o) {
                alert("Webservise baglanilamadi.Yoneticiye basvurunuz.");
                console.log(o);
            }
        });
    }
</script>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Baylan</title>
    <style type="text/css">
        html, body {
            margin: 0;
            padding: 0;
            background: #f0f7fb;
            font-family: Tahoma, Arial, sans-serif;
            color: #1e293b;
        }
        .wrap {
            width: 560px;
            margin: 10px auto;
            padding: 12px 14px 14px;
            background: #ffffff;
            border: 2px solid #bae6fd;
            border-radius: 14px;
        }
        .support {
            margin: 0 0 8px 0;
            padding: 8px 12px;
            background: #0e7490;
            color: #ffffff;
            border-radius: 10px;
            text-align: center;
        }
        .support-label {
            margin: 0;
            font-size: 13px;
            font-weight: bold;
        }
        .support-phone {
            margin: 2px 0 0 0;
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .support-hint {
            margin: 2px 0 0 0;
            font-size: 12px;
        }
        .title {
            margin: 0 0 8px 0;
            padding: 14px 12px;
            background: #0e7490;
            color: #ffffff;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
        }
        .message {
            min-height: 36px;
            margin: 0 0 8px 0;
            padding: 6px 8px;
            font-size: 16px;
            line-height: 1.3;
            text-align: center;
            color: #9f1239;
        }
        .btn {
            display: block;
            width: 100%;
            height: 64px;
            margin: 0 0 8px 0;
            border: 0;
            border-radius: 10px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 22px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-read {
            background: #0e7490;
            color: #ffffff;
        }
        .btn-load {
            background: #d97706;
            color: #ffffff;
        }
        .btn-cancel {
            background: #ffffff;
            color: #b91c1c;
            border: 2px solid #fecaca;
            height: 52px;
            font-size: 18px;
        }
        .btn-go {
            background: #1e5a9e;
            color: #ffffff;
            height: 58px;
            font-size: 20px;
            margin: 0;
        }
        .warn {
            margin: 2px 0 8px 0;
            text-align: center;
            color: #b91c1c;
            font-size: 14px;
            font-weight: bold;
        }
        #kioskDonusMesaji {
            display: none;
            margin-top: 6px;
            padding: 8px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }
        .go-box {
            margin-top: 4px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .go-hint {
            margin: 0 0 6px 0;
            color: #475569;
            font-size: 13px;
            font-weight: bold;
        }
    </style>
</head>
<body onload="formload()">
    <form id="form2" runat="server">
        <tr style="display:none">
            <td>
                <asp:TextBox ID="txtKartIDS" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtBakiye" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtAboneNo" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtDonem" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtGensicilno" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtIlkEndeks" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtYuklenecek" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtAtikSuMik" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtError" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKartTipi" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi1" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi2" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKatSayi3" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKademe1" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtKademe2" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtTipAcik" runat="server" style="display:none"></asp:TextBox>
                <asp:TextBox ID="txtCihazNo" runat="server" style="display:none"></asp:TextBox>
            </td>
        </tr>
    </form>

    <form id="form1">
        <div style="display:none">
            <input id="txtKartId" name="txtKartId" />
            <input id="txtYukle" name="txtYukle" />
            <input id="txtAboneNoC" name="txtAboneNoC" />
            <input id="txtDonemC" name="txtDonemC" />
            <input id="txtGensicilnoC" name="txtGensicilnoC" />
            <input id="txtIlkEndeksC" name="txtIlkEndeksC" />
            <input id="txtYuklenecekC" name="txtYuklenecekC" />
            <input id="txtAtikSuMikC" name="txtAtikSuMikC" />
        </div>

        <div class="wrap">
            <div class="support">
                <p class="support-label">Destek icin</p>
                <p class="support-phone">444 01 39</p>
                <p class="support-hint">nolu hatti arayabilirsiniz</p>
            </div>

            <div class="title">Avans Kredi Yukleme</div>

            <div class="message">
                <asp:Label ID="lblSMesaj" runat="server" ForeColor="maroon" />
                <div id="kioskDonusMesaji"></div>
            </div>

            <button id="btnKartOku" type="submit" class="btn btn-read" onclick="btnKartOku_Click()">KART OKU</button>
            <button id="btnYukle" type="button" class="btn btn-load" style="display:none" onclick="btnYukle_Click()">AVANS YUKLE</button>
            <button id="btnGiris" type="button" class="btn btn-cancel" onclick="btnGiris_Click(); return false;">IPTAL</button>

            <p class="warn">Isleminiz bitene kadar karti yerinden oynatmayiniz.</p>

            <div id="goBox" class="go-box">
                <p class="go-hint">Borc Sorgulama (10.0.1.1/kiosk/public)</p>
                <button id="btnKioskGit" type="button" class="btn btn-go" onclick="chromeKioskaGit(); return false;">GIT</button>
            </div>
        </div>
    </form>

    <object name="secondobj" style="display:none" id="TestActivex" classid="CLSID:E7CFB476-B06D-30FA-BA42-FBD9485BB296"></object>
</body>
</html>
