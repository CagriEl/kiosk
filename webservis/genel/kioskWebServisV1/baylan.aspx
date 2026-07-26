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
    var KIOSK_STATS_HIT = "http://10.0.1.1/kiosk/public/api/kiosk/stats/hit";
    var EBELEDIYE_URL = "https://e-belediye.kirklareli.bel.tr";
    var WINDOWS_7 = navigator.userAgent.indexOf("Windows NT 6.1") >= 0;

    function kioskAnaSayfayaDonusBaslat() {
        if (kioskDonusZamanlayici != null) {
            return;
        }

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
            donusMesaji.innerHTML = kalanSaniye + " saniye sonra bor\u00e7 sorgulama ekran\u0131na d\u00f6n\u00fclecektir.";
        }

        kioskDonusSayaci = window.setInterval(function () {
            kalanSaniye = kalanSaniye - 1;
            if (donusMesaji && kalanSaniye > 0) {
                donusMesaji.innerHTML = kalanSaniye + " saniye sonra bor\u00e7 sorgulama ekran\u0131na d\u00f6n\u00fclecektir.";
            }
            if (kalanSaniye <= 0) {
                window.clearInterval(kioskDonusSayaci);
            }
        }, 1000);

        kioskDonusZamanlayici = window.setTimeout(function () {
            chromeKioskaGit();
        }, 15000);
    }

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

    function kayitBasariliYukleme() {
        try {
            var img = new Image();
            img.src = KIOSK_STATS_HIT + "?type=avans_success&t=" + (new Date().getTime());
        } catch (e) { }
    }

    function showDebtBlock(serverMsg) {
        var panel = document.getElementById("debtBlock");
        var detail = document.getElementById("debtBlockDetail");
        var actions = document.getElementById("mainActions");
        if (detail) {
            detail.innerHTML = serverMsg
                ? serverMsg
                : "\u00d6denmemi\u015f borcunuz bulundu\u011fu i\u00e7in avans kredi y\u00fcklemesi yap\u0131lamaz.";
        }
        if (panel) panel.style.display = "block";
        if (actions) actions.style.display = "none";
        var btnYukle = document.getElementById("btnYukle");
        var btnKart = document.getElementById("btnKartOku");
        if (btnYukle) btnYukle.style.display = "none";
        if (btnKart) btnKart.style.display = "none";
    }

    function showSuccess(html) {
        var box = document.getElementById("successBox");
        var msg = document.getElementById("lblSMesaj");
        if (msg) msg.innerHTML = html;
        if (box) box.style.display = "block";
        var actions = document.getElementById("mainActions");
        if (actions) actions.style.display = "none";
        var warn = document.getElementById("cardWarn");
        if (warn) warn.style.display = "none";
    }

    function formload() {
        if (WINDOWS_7) {
            var goBox = document.getElementById("goBox");
            if (goBox) goBox.style.display = "none";
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
            showDebtBlock(document.getElementById("txtError").value);
        }
    }

    function btnKartOku_Click() {
        var objKontrol = document.getElementById("TestActivex");
        var islemSonuc = objKontrol.KartSeriNoOku();
        if (islemSonuc) {
            document.getElementById("txtKartId").value = objKontrol.KartBilgisi.KartSeriNo;
        } else {
            alert('Kart Okunamad\u0131.');
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
            alert('Kart de\u011fi\u015ftirilmi\u015f.');
            return false;
        }

        if (islemSonuc) {
            document.getElementById("txtKartId").value = obj.KartBilgisi.KartSeriNo;
        } else {
            alert('Kart Okunamad\u0131.');
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

        strSQLKontrol = "select isnull((select aboneno";
        strSQLKontrol = strSQLKontrol + " from suabone where kartId='" + document.getElementById("txtKartId").value + "'";
        strSQLKontrol = strSQLKontrol + " and exists(select * from gtttah where modulno=24 and exists (select * from subeyan where tahnot='Kartl" + "\u0131" + " Abone Avans Kont" + "\u00f6" + "r'";
        strSQLKontrol = strSQLKontrol + " and subeyan.aboneno=suabone.aboneno and subeyan.gensicilno=suabone.gensicilno and recid=beyan_id) and bakiye>0)),0) aboneno";
        var dahaOnceVarMi = degerOku(strSQLKontrol);
        if (dahaOnceVarMi != 0) {
            showDebtBlock("\u00d6denmemi\u015f avans kont\u00f6r borcunuz vard\u0131r; y\u00fckleme yap\u0131lamaz.");
            return false;
        }
        else {
            flag = 1;
            if (obj.KrediYukle(Kredi, YedekKredi)) {
                vtYaz();
            }
            else {
                alert('Kont\u00f6r yaz\u0131m\u0131 ger\u00e7ekle\u015ftirilemedi. (Hata Kodu:' + obj.SonHataMesaji + ')');
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
                    alert("\u0130\u015flem ba\u015far\u0131s\u0131z. Y\u00f6neticiye ba\u015fvurun.");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }
            },
            error: function (o) {
                alert("Webservise ba\u011flan\u0131lamad\u0131. Y\u00f6neticiye ba\u015fvurunuz.");
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
                    var ton = document.getElementById("txtYuklenecek").value;
                    showSuccess(
                        "Y\u00fcklenen avans kredi miktar\u0131 <strong>" + ton + " ton</strong>dur.<br/>" +
                        "7 g\u00fcn i\u00e7erisinde " + ton + " ton su tutar\u0131n\u0131 \u00f6demedi\u011finiz takdirde gecikme zamm\u0131 uygulanacakt\u0131r.<br/>" +
                        "\u0130yi g\u00fcnler dileriz."
                    );
                    kayitBasariliYukleme();
                    kioskAnaSayfayaDonusBaslat();
                }
                else {
                    alert("\u0130\u015flem ba\u015far\u0131s\u0131z. Y\u00f6neticiye ba\u015fvurun.");
                    window.location.href = '/Belsis-Net/genel/kioskWebServisV1/baylan.aspx';
                }
            },
            error: function (o) {
                alert("Webservise ba\u011flan\u0131lamad\u0131. Y\u00f6neticiye ba\u015fvurunuz.");
                console.log(o);
            }
        });
    }
</script>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Avans Kredi · Kırklareli Belediyesi</title>
    <style type="text/css">
        html, body {
            margin: 0; padding: 0; height: 100%;
            background: linear-gradient(165deg, #0b3a6e 0%, #1e5a9e 42%, #0ea5e9 100%);
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: #0f172a;
        }
        .shell {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px 16px 28px;
        }
        .card {
            width: 640px;
            max-width: 96%;
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 24px 60px rgba(8, 35, 70, 0.35);
            overflow: hidden;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 22px;
            background: linear-gradient(90deg, #123a6b, #1e5a9e);
            color: #fff;
        }
        .brand img {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: #fff;
            object-fit: contain;
            box-shadow: 0 4px 14px rgba(0,0,0,.25);
        }
        .brand h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .02em;
            line-height: 1.2;
        }
        .brand p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: .9;
            font-weight: 600;
        }
        .body {
            padding: 20px 22px 18px;
        }
        .support {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            border-radius: 14px;
            background: #ecfeff;
            border: 1px solid #a5f3fc;
        }
        .support .lbl { font-size: 12px; color: #0e7490; font-weight: 700; margin: 0; }
        .support .phone { font-size: 26px; font-weight: 800; color: #0e7490; margin: 2px 0 0; letter-spacing: 1px; }
        .support .hint { font-size: 12px; color: #475569; margin: 0; text-align: right; max-width: 210px; line-height: 1.35; }
        .title {
            margin: 0 0 14px;
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: #123a6b;
        }
        .message {
            min-height: 28px;
            margin: 0 0 12px;
            text-align: center;
            font-size: 15px;
            line-height: 1.4;
            color: #9f1239;
        }
        #successBox {
            display: none;
            margin: 0 0 14px;
            padding: 16px;
            border-radius: 16px;
            background: #ecfdf5;
            border: 2px solid #6ee7b7;
            color: #065f46;
            text-align: center;
            font-size: 16px;
            line-height: 1.45;
            font-weight: 600;
        }
        #kioskDonusMesaji {
            display: none;
            margin-top: 10px;
            padding: 10px 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
        }
        #debtBlock {
            display: none;
            margin: 0 0 14px;
            padding: 18px 16px;
            border-radius: 16px;
            background: #fff7ed;
            border: 2px solid #fdba74;
            text-align: center;
        }
        #debtBlock .icon {
            width: 56px; height: 56px; margin: 0 auto 10px;
            border-radius: 50%;
            background: #ffedd5;
            border: 2px solid #fb923c;
            line-height: 52px;
            font-size: 28px;
            font-weight: 800;
            color: #c2410c;
        }
        #debtBlock h2 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #9a3412;
        }
        #debtBlockDetail {
            margin: 0 0 12px;
            font-size: 14px;
            color: #7c2d12;
            line-height: 1.45;
            font-weight: 600;
        }
        #debtBlock .paybox {
            margin: 0 auto;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #fed7aa;
            max-width: 420px;
        }
        #debtBlock .paybox .s {
            margin: 0 0 4px;
            font-size: 12px;
            color: #78716c;
            font-weight: 600;
        }
        #debtBlock .paybox .u {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #1e5a9e;
            word-break: break-all;
        }
        #debtBlock .paybox .n {
            margin: 8px 0 0;
            font-size: 13px;
            color: #44403c;
            line-height: 1.4;
        }
        .btn {
            display: block;
            width: 100%;
            height: 68px;
            margin: 0 0 10px;
            border: 0;
            border-radius: 20px;
            -webkit-border-radius: 20px;
            -moz-border-radius: 20px;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 22px;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: .02em;
        }
        .btn-read { background: #1e5a9e; color: #fff; box-shadow: 0 8px 18px rgba(30,90,158,.35); border-radius: 20px; }
        .btn-load { background: #0e7490; color: #fff; box-shadow: 0 8px 18px rgba(14,116,144,.35); border-radius: 20px; }
        .btn-cancel {
            background: #fff;
            color: #b91c1c;
            border: 2px solid #fecaca;
            height: 54px;
            font-size: 18px;
            box-shadow: none;
            border-radius: 20px;
            -webkit-border-radius: 20px;
        }
        .btn-go {
            background: #123a6b;
            color: #fff;
            height: 58px;
            font-size: 18px;
            margin: 0;
            border-radius: 20px;
            -webkit-border-radius: 20px;
        }
        .warn {
            margin: 4px 0 12px;
            text-align: center;
            color: #b45309;
            font-size: 14px;
            font-weight: 700;
        }
        .go-box {
            margin-top: 6px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .go-hint {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
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

        <div class="shell">
            <div class="card">
                <div class="brand">
                    <img src="http://10.0.1.1/kiosk/public/images/logo.png" alt="T.C. Kirklareli Belediyesi" />
                    <div>
                        <h1>T.C. Kırklareli Belediye Başkanlığı</h1>
                        <p>Baylan · Avans Kredi Yükleme</p>
                    </div>
                </div>

                <div class="body">
                    <div class="support">
                        <div>
                            <p class="lbl">Destek hattı</p>
                            <p class="phone">444 01 39</p>
                        </div>
                        <p class="hint">Yardım için bu numarayı arayabilirsiniz</p>
                    </div>

                    <h2 class="title">Avans Kredi Yükleme</h2>

                    <div id="debtBlock">
                        <div class="icon">!</div>
                        <h2>Yükleme yapılamaz</h2>
                        <p id="debtBlockDetail"></p>
                        <div class="paybox">
                            <p class="s">Ödemenizi şu adresten yaptıktan sonra avans kredi alabilirsiniz</p>
                            <p class="u">e-belediye.kirklareli.bel.tr</p>
                            <p class="n">https://e-belediye.kirklareli.bel.tr</p>
                        </div>
                    </div>

                    <div id="successBox">
                        <asp:Label ID="lblSMesaj" runat="server" />
                        <div id="kioskDonusMesaji"></div>
                    </div>

                    <div id="mainActions">
                        <button id="btnKartOku" type="submit" class="btn btn-read" onclick="btnKartOku_Click()">KART OKU</button>
                        <button id="btnYukle" type="button" class="btn btn-load" style="display:none" onclick="btnYukle_Click()">AVANS YÜKLE</button>
                        <button id="btnGiris" type="button" class="btn btn-cancel" onclick="btnGiris_Click(); return false;">İPTAL</button>
                        <p id="cardWarn" class="warn">İşleminiz bitene kadar kartı yerinden oynatmayınız.</p>
                    </div>

                    <div id="goBox" class="go-box">
                        <p class="go-hint">Borç sorgulama ekranına dön</p>
                        <button id="btnKioskGit" type="button" class="btn btn-go" onclick="chromeKioskaGit(); return false;">GİT</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <object name="secondobj" style="display:none" id="TestActivex" classid="CLSID:E7CFB476-B06D-30FA-BA42-FBD9485BB296"></object>
</body>
</html>
