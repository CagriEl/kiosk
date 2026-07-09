<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="kontorYukleme.aspx.cs" Inherits="kiosk.kontorYukleme" %>

<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server"></asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">

    <script type="text/javascript">

        var obj = new ActiveXObject("MSCInterface.Operations");
        var AnaKredi, YedekKredi, SayacNo, AboneNO, AboneNO2;

        var tonajList = [ "5", "10", "20", "30", "40", "50" ];
        var tonaj = 0;

        var suAboneNo, gensicilno, adi, soyadi, sonTarih, okumaSayisi, sonDonem, kartSayi, ilkEndeks, bakiye, aboneTipi, tipAciklama;
        var sonOdemeTarihi, tutara, tutatik, tutdiger, tutfatura, tutharsu, tutkdv, yuklenecek;

        var tableDetayHTML;
        var tableHTML;
        
        function tonajCalc(arg) {
            if (arg == '-' && tonaj != 0) {
                tonaj = tonaj - 1;
                $('#txttonaj').val(tonajList[tonaj]);
                kontorSecildi(tonajList[tonaj]);
            }
            else if (arg == '+' && tonaj + 1 <= tonajList.length - 1) {
                tonaj = tonaj + 1;
                $('#txttonaj').val(tonajList[tonaj]);
                kontorSecildi(tonajList[tonaj]);
            }  
        }

        function kartOku() {
            yukleniyor.goster("Lütfen bekleyiniz..", {
                onShow: function () {
                    var aboneBilgi = obj.AboneOku();
                    if (aboneBilgi.length >= 227) {
                        AnaKredi = aboneBilgi.substring(0, 10);
                        YedekKredi = aboneBilgi.substring(10, 20);
                        SayacNo = aboneBilgi.substring(30, 40);
                        AboneNO = aboneBilgi.substring(40, 50)
                        AboneNO2 = parseInt(AboneNO, 10);
                        //AboneNO2 = 27126;
                        $.ajax({
                            type: "POST",
                            url: "avansYukleme.aspx/suAboneNoSorgula",
                            data: "{ suAboneNo: '" + AboneNO2 + "' }",
                            contentType: "application/json",
                            dataType: "json",
                            success: function (cevap) {                                
                                gensicilno = cevap.d.gensicilno;
                                adi = cevap.d.adi;
                                soyadi = cevap.d.soyadi
                                //sonTarih = cevap.d.sonTarih;
                                okumaSayisi = cevap.d.okumaSayisi;
                                sonDonem = cevap.d.sonDonem;
                                //kartSayi = cevap.d.kartSayi;
                                ilkEndeks = cevap.d.ilkEndeks;
                                //bakiye = cevap.d.bakiye;
                                aboneTipi = cevap.d.aboneTipi;
                                //tipAciklama = cevap.d.tipAciklama;

                                $('#formBody1').css("display", "none");
                                $('#formBody2').css("display", "");

                                $('#devam1').css("display", "none");
                                $('#devam2').css("display", "");

                                $('#adim').text("ADIM 2");

                                $('#navtab-1').attr("class", "list-group-item");
                                $('#navtab-2').attr("class", "list-group-item active");
                                $('#navtab-3').attr("class", "list-group-item");
                                $('#navtab-4').attr("class", "list-group-item");
                                $('#navtab-5').attr("class", "list-group-item");

                                document.getElementById("baslik2").innerHTML = "Sn. " + adi + " " + soyadi + ", <b>" + AboneNO2 + "</b> numaralı su aboneliğinize kontör yüklemesi yapılacaktır. <br/>Lütfen işlem süresince kartınızı bulunduğu yerden almayınız. <br/><br/>Yüklemek istediğiniz kontör miktarını seçiniz.";                                
                                $('#adim').text("ADIM 2");                                
                                kontorSecildi(tonajList[0]);
                                //yukleniyor.gizle();
                            },
                            error: function (cevap) {
                                yukleniyor.gizle();
                                alert("Kart Okunamadı. Lütfen kartınızı ilgili alana koyup tekrar deneyiniz.");
                            }
                        });

                    }
                    else {
                        yukleniyor.gizle();
                        alert("Kart okuma hatası. Lütfen tekrar deneyiniz.");
                    }
                }
            });
        }

        function kontorSecildi(args) {

            yuklenecek = args;

            if (args == 0) {
                //$('#odemeDetayTablo').css("display", "none");
            }

            else {
                yukleniyor.goster("Hesaplanıyor..", { onShow: function () { } } );

                $('#detayGoster').css("display", "");
                $('#detayGizle').css("display", "none");

                //$('#odemeDetayTabloBody').innerHTML = "";                
                $.ajax({
                    type: "POST",
                    url: "kontorYukleme.aspx/suHesapla",
                    data: "{ suAboneNo:'" + AboneNO2 + "', donem:'" + sonDonem + "', okumaSayisi:'" + okumaSayisi + "', yuklenecek:'" + args + "', aboneTipi:'" + aboneTipi + "' }",
                    contentType: "application/json",
                    dataType: "json",
                    success: function (cevap) {
                        //sonOdemeTarihi = cevap.d.sonOdemeTarihi;
                        //tutara = cevap.d.tutara;
                        //tutatik = cevap.d.tutatik;
                        //tutdiger = cevap.d.tutdiger;
                        tutfatura = cevap.d.tutfatura;
                        //tutharsu = cevap.d.tutharsu;
                        //tutkdv = cevap.d.tutkdv;

                        /*tableDetayHTML = "<tr><td>1</td><td>Su Tutarı</td><td>" + tutharsu + " TL</td></tr>" +
                            "<tr><td>2</td><td>Atık Su Tutarı</td><td>" + tutatik + " TL</td></tr>" +
                            "<tr><td>3</td><td>Diğer Ücretler</td><td>" + tutdiger + " TL</td></tr>" +
                            "<tr><td>4</td><td>KDV Tutarı</td><td>" + tutkdv + " TL</td></tr>" +
                            "<tr><td></td><td><b>Toplam Ödenecek</b></td><td><b>" + tutfatura + " TL</b></td></tr>";
        
                        tableHTML = "<tr><td>#</td><td>Kontör Yükleme</td><td>" + tutfatura + " TL</td></tr>";
        
                        $('#odemeDetayTabloBody').innerHTML = tableHTML;
                        $('#odemeDetayTablo').css("display", "");*/
                        $('#txttutar').val(tutfatura + " TL");
                        $('#kartCekilecekTutar').val(tutfatura + " TL");
                        yukleniyor.gizle();
                    },
                    error: function (cevap) {
                        alert("İşlem Yapılamadı. Ödeme parametreleri getirilemedi. Belediye personeli ile iletişime geçiniz.");
                        yukleniyor.gizle();
                    }
                });                                   
            }
        }

        function kartBilgileriAc() {
            yilHesapla();

            $('#adim').text("ADIM 3");
            $('#formBody1').css("display", "none");
            $('#formBody2').css("display", "none");
            $('#formBody3').css("display", "");

            $('#devam1').css("display", "none");
            $('#devam2').css("display", "none");
            $('#devam3').css("display", "");


            $('#navtab-1').attr("class", "list-group-item");
            $('#navtab-2').attr("class", "list-group-item");
            $('#navtab-3').attr("class", "list-group-item active");
            $('#navtab-4').attr("class", "list-group-item");
            $('#navtab-5').attr("class", "list-group-item");


            $('#kartAdiSoyadi').focus();
            $('#kartAdiSoyadi').val(adi + " " + soyadi);
        }

        function islemOnay() {
            var kartAdiSoyadi, kartNo, kartTarih, kartCCV;
            kartAdiSoyadi = $('#kartAdiSoyadi').val();
            kartNo = $('#kartNo').val();
            kartTarih = $('#kartAy').val() + '/' + $('#kartYil').val();
            kartCCV = $('#kartCCV').val();

            if (kartAdiSoyadi && kartNo && kartCCV) {

                yukleniyor.goster("İşlem yapılıyor..", {
                    onShow: function () {
                        $.ajax({
                            type: "POST",
                            url: "kontorYukleme.aspx/kontorYukle",
                            data: "{ 'sonDonem': '" + sonDonem + "', 'suAboneNo': '" + AboneNO2 + "', 'gensicilno': '" + gensicilno + "', 'ilkEndeks': '" + ilkEndeks + "', 'yuklenecek': '" + yuklenecek +
                                "', 'okumaSayisi': '" + okumaSayisi + "', 'tahSekli': '4', 'aboneTipi': '" + aboneTipi + "', 'tutar': '" + tutfatura.replace(".", "").replace(",", ".") + "'," +
                                "'kartAdiSoyadi': '" + kartAdiSoyadi + "'," +
                                "'kartNo': '" + kartNo + "'," +
                                "'kartTarih': '" + kartTarih + "'," +
                                "'kartCCV': '" + kartCCV + "' }",
                            contentType: "application/json",
                            dataType: "json",
                            success: function (cevap) {

                                yukleniyor.gizle();

                                if (cevap.d.sonucKodu == 1001) {
                                    var formElement = $("#bankPost");
                                    formElement.attr("action", cevap.d.threeDUrl);

                                    for (key in cevap.d) {
                                        if (typeof (cevap.d[key]) == "object" && cevap.d[key].hasOwnProperty("send")) {
                                            if (cevap.d[key]["send"]) {
                                                formElement.append("<input type='hidden' name='" + cevap.d[key]["caption"] + "' value='" + cevap.d[key]["data"] + "'>");
                                            }
                                        }
                                    }

                                    var retFunction = function () {
                                        formElement.submit();
                                    }

                                    $('#navtab-1').attr("class", "list-group-item");
                                    $('#navtab-2').attr("class", "list-group-item");
                                    $('#navtab-3').attr("class", "list-group-item");
                                    $('#navtab-4').attr("class", "list-group-item active");
                                    $('#navtab-5').attr("class", "list-group-item");

                                    var devam = confirm("3D Şifrenizi Girmek İçin Banka Sunucularına Yönlendirileceksiniz. İşlemi Onaylıyor musunuz?");
                                    if (devam == true) {
                                        yukleniyor.goster();
                                        retFunction();
                                    }
                                    else {
                                        yukleniyor.goster();
                                        window.location.href = "default.aspx";
                                    }
                                } else {
                                    var formElement = $("#bankPost");
                                    formElement.attr("action", cevap.d.threeDUrl);

                                    for (key in cevap.d) {
                                        if (typeof (cevap.d[key]) == "object" && cevap.d[key].hasOwnProperty("send")) {
                                            if (cevap.d[key]["send"]) {
                                                formElement.append("<input type='hidden' name='" + cevap.d[key]["caption"] + "' value='" + cevap.d[key]["data"] + "'>");
                                            }
                                        }
                                    }

                                    var retFunction = function () {
                                        formElement.submit();
                                    }

                                    var devam = confirm("Kartınız 3D secure programında dahil değildir. Yarım 3D işlemi yapılacak. Onaylıyormusunuz?");
                                    if (devam == true) {
                                        yukleniyor.goster();
                                        retFunction();
                                    }
                                }
                                
                                if (cevap.d.sonucKodu != "0") {
                                    console.log(cevap)
                                }
                                else {
                                    alert("İşlem Yapılamadı. Belediye personeli ile iletişime geçiniz.");
                                }
                            },
                            error: function (cevap) {
                                alert("İşlem Yapılamadı. Belediye personeli ile iletişime geçiniz.");
                            }
                        });
                    }
                });

            }
            else {
                alert("Devam edebilmek için kart bilgilerinizi eksiksiz girmeniz gerekmektedir.");
            }
        }

        function islemTamamlandi() {
            $('#adim').text("ADIM 4");
            $('#formBody3').css("display", "none");
            $('#formBody4').css("display", "");
            $('#devam3').css("display", "none");
            $('#devam4').css("display", "");
        }

        function bitti() {
            window.location.href = "default.aspx";
        }

        function yilHesapla() {
            select = document.getElementById('kartYil');
            mevcutYil = new Date().getFullYear();

            for (var i = mevcutYil; i < mevcutYil + 10; i++) {
                var opt = document.createElement('option');
                opt.value = i.toString().substr(2);
                opt.innerHTML = i.toString().substr(2);
                select.appendChild(opt);
            }
        }

    </script>

    <div class="portlet light bordered" id="form_wizard_1" style="width: 80%; margin: auto;">
        <div class="portlet-title">
            <div class="caption">
                <i class=" icon-layers font-red"></i>
                <span class="caption-subject font-red bold uppercase">Kontör Yükleme -
                   
                    <span id="adim" class="step-title">Adım 1 </span>
                </span>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="form-horizontal" id="submit_form">
                <div class="form-wizard">

                    <div id="formBody1" class="form-body">
                        
                        <div class="tab-pane active" id="tab1" style="text-align: center;">
                            <h4 class="block">Lütfen kartınızı ilgili alana koyup, devam et butonuna basınız.</h4>
                        </div>

                    </div>

                    <div id="formBody2" class="form-body" style="display:none;">

                        <div class="tab-pane active" id="tab2" style="text-align: center;">
                            <h4 id="baslik2" class="block"></h4>

                            <input type="button" value="-" class="minusPlusButton" onclick="tonajCalc('-');" />
                            <input id="txttonaj" type="text" value="5" style="height:45px;font-size:30px;text-align:center;width:60px;" disabled />
                            <input type="button" value="+" class="minusPlusButton" onclick="tonajCalc('+');" />

                            <!--
                            <div id="odemeDetayTablo" style="width: 80%; margin: auto; display: none; margin-top:20px;">
                                <div class="portlet light portlet-fit bordered">
                                    <div class="portlet-body">
                                        <div class="table-scrollable table-scrollable-borderless">
                                            <table class="table table-hover table-light">
                                                <thead>
                                                    <tr class="uppercase">
                                                        <th style="width: 10%">NO </th>
                                                        <th style="width: 50%">GELIR ADI </th>
                                                        <th style="width: 40%">Tutar </th>
                                                    </tr>
                                                </thead>
                                                <tbody style="text-align: left;" id="odemeDetayTabloBody">
                                                </tbody>
                                            </table>

                                            <a id="detayGoster" href="javascript:tableDetayGoster();" class="btn default" style="width: 150px; font-size: 12px; padding-top: 7px; margin-top: 25px;">Detay Göster</a>
                                            <a id="detayGizle" href="javascript:tableDetayGizle();" class="btn default" style="width: 150px; font-size: 12px; padding-top: 7px; margin-top: 25px; display: none;">Detay Gizle</a>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            -->
                           


                            <div style="margin-top:20px;">
                                <input type="text" id="txttutar" style="height:45px;font-size:30px;text-align:center;width:250px;" disabled />
                            </div>

                        </div>
                       
                    </div>

                    <div id="formBody3" class="form-body" style="display:none;">
                        
                        <div class="tab-pane active" id="tab3" style="text-align: center;">
                            <h4 class="block">Lütfen kredi kartı bilgilerinizi giriniz.</h4>

                            <div class="form-group" style="width:80%">
                                <label class="control-label col-md-4">Kart Sahibi</label>
                                <div class="col-md-4">
                                    <input type="text"  class="form-control" id="kartAdiSoyadi" tabindex="0" style="font-size:24px;width:500px;height:45px;" />
                                </div>
                            </div>

                            <div class="form-group" style="width:80%">
                                <label class="control-label col-md-4">Kart Numarası</label>
                                <div class="col-md-4">
                                    <%--<input type="password" class="form-control" id="kartNo" tabindex="1" maxlength="16" style="font-size:24px;width:500px;height:45px;" value="4938410160702981" />--%>
                                    <input type="password" class="form-control" id="kartNo" tabindex="1" maxlength="16" style="font-size:24px;width:500px;height:45px;" value="" />
                                </div>
                            </div>

                            <div class="form-group" style="width:80%">
                                <label class="control-label col-md-4">Son Kullanım Tarihi</label>
                                <div class="col-md-4" style="text-align:left;">
                                    <select id="kartAy" style="font-size:24px;width:70px;text-align:center;height:45px;">
                                        <option value="01" selected>01</option>
                                        <option value="02">02</option>
                                        <option value="03">03</option> <!--Test Kart Tarihi-->
                                        <option value="04">04</option>
                                        <option value="05">05</option>
                                        <option value="06">06</option>
                                        <option value="07">07</option>
                                        <option value="08">08</option>
                                        <option value="09">09</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                                    <span style="font-size:40px;">&nbsp;/&nbsp;</span>
                                    <select id="kartYil" style="font-size:24px;width:70px;text-align:center;height:45px;">

                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="width:80%">
                                <label class="control-label col-md-4">Güvenlik Kodu</label>
                                <div class="col-md-4">
                                    <!--Test Kart Güvenlik Kodu : -->
                                    <input type="password" class="form-control" id="kartCCV" tabindex="1" maxlength="3" style="font-size:24px;height:45px;" value="" />
                                </div>
                            </div>

                            <div class="form-group" style="width:80%">
                                <label class="control-label col-md-4">Çekilecek Tutar</label>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" id="kartCekilecekTutar" tabindex="0" style="font-size:24px;height:45px;" disabled />
                                </div>
                            </div>
                        </div>

                    </div>

                    <div id="formBody4" class="form-body" style="display:none;">
                        
                        <div class="tab-pane active" id="tab4" style="text-align: center;">
                            <h4 class="block">İşlem tamamlandı, kartınızı alabilirsiniz.</h4>
                        </div>

                    </div>

                    <div class="form-actions" style="text-align: center;">
                        
                        <input id="devam1" onclick="kartOku();" type="button" value="Devam Et" class="altButon" />
                        <input id="devam2" onclick="kartBilgileriAc();" type="button" value="Devam Et" class="altButon" style="display:none;" />
                        <input id="devam3" onclick="islemOnay();" type="button" value="Onayla" class="altButon" style="display:none;" />
                        <input id="devam4" onclick="bitti();" type="button" value="Bitti" class="altButon" style="display:none;" />

                    </div>
                </div>
            </div>
        </div>
    </div> 
    <!-- sol nav -->
    <div class="list-group">
        <a id="navtab-1" class="list-group-item active">1. Kart Okuma</a>
        <a id="navtab-2" class="list-group-item">2. Kontör Seçimi</a>
        <a id="navtab-3" class="list-group-item">3. Kredi Kartı Bilgileri</a>
        <a id="navtab-4" class="list-group-item">4. 3D Secure Onaylama</a>
        <a id="navtab-5" class="list-group-item">5. İşlem Sonucu</a>
    </div>
    <!-- sol nav -->

    <form id="bankPost" method="post" action="" target="_self"></form>
    
</asp:Content>
