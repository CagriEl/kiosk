<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="avansYukleme.aspx.cs" Inherits="kiosk.avansYukleme" %>

<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server"></asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">

    <script type="text/javascript">

        var obj = new ActiveXObject("MSCInterface.Operations");

        var AnaKredi, YedekKredi, SayacNo, AboneNO, AboneNO2;
        var suAboneNo, gensicilno, adi, soyadi, sonTarih, okumaSayisi, sonDonem, kartSayi, ilkEndeks, bakiye, aboneTipi, tipAciklama;
        var sonOdemeTarihi, tutara, tutatik, tutdiger, tutfatura, tutharsu, tutkdv, yuklenecek;

        function kartOku() {

            yukleniyor.goster('Lütfen bekleyiniz..', {
                onShow: function () {
                    var aboneBilgi = obj.AboneOku();

                    if (aboneBilgi.length >= 227) {
                        AnaKredi = aboneBilgi.substring(0, 10);
                        YedekKredi = aboneBilgi.substring(10, 20);
                        SayacNo = aboneBilgi.substring(30, 40);
                        AboneNO = aboneBilgi.substring(40, 50)
                        AboneNO2 = parseInt(AboneNO, 10);
                       /*if (1 == 1) {
                
                        AboneNO2 = 27126;*/
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
                                sonTarih = cevap.d.sonTarih;
                                okumaSayisi = cevap.d.okumaSayisi;
                                sonDonem = cevap.d.sonDonem;
                                kartSayi = cevap.d.kartSayi;
                                ilkEndeks = cevap.d.ilkEndeks;
                                bakiye = cevap.d.bakiye;
                                aboneTipi = cevap.d.aboneTipi;
                                tipAciklama = cevap.d.tipAciklama;

                                $('#formBody1').css("display", "none");
                                $('#formBody2').css("display", "");

                                $('#devam1').css("display", "none");
                                $('#devam2').css("display", "");

                                $('#adim').text("ADIM 2");

                                $('#navtab-1').attr("class", "list-group-item");
                                $('#navtab-2').attr("class", "list-group-item active");
                                $('#navtab-3').attr("class", "list-group-item");

                                document.getElementById("baslik2").innerHTML = "Sn. " + adi + " " + soyadi + ", <b>" + AboneNO2 + "</b> numaralı su aboneliğinize avans kredi yüklemesi yapılacaktır. <br/>Lütfen işlem süresince kartınızı bulunduğu yerden almayınız.";
                                yukleniyor.gizle();
                            },
                            error: function (cevap) {
                                alert("Kart Okunamadı. Lütfen kartınızı ilgili alana koyup tekrar deneyiniz.");
                                yukleniyor.gizle();
                            }
                        });

                    }
                    else {
                        alert("Kart okuma hatası. Lütfen tekrar deneyiniz.");
                        yukleniyor.gizle();
                    }
                }
            });
        }

        function avansYukle() {

            //yuklenecek = 3;

            yukleniyor.goster("Lütfen bekleyiniz..", {
                onShow: function () {

                    $.ajax({
                        type: "POST",
                        url: "avansYukleme.aspx/beyanYaz",
                        data: "{ ilkEnd:'" + ilkEndeks + "', donem:'" + sonDonem + "', aboneno:'" + AboneNO2 + "', gensicilno:'" + gensicilno + "', sondonem:'" +
                            sonDonem + "', abonetipi:'" + aboneTipi + "', okumaSayisi:'" + okumaSayisi + "', tahSekli:'5' }", //tahsekli 5 avans yükleme
                        contentType: "application/json",
                        dataType: "json",
                        success: function (cevap) {

                            if (cevap.d > "0") {

                                $('#formBody1').css("display", "none");
                                $('#formBody2').css("display", "none");
                                $('#formBody3').css("display", "");

                                $('#devam1').css("display", "none");
                                $('#devam2').css("display", "none");
                                $('#devam3').css("display", "");

                                $('#navtab-1').attr("class", "list-group-item");
                                $('#navtab-2').attr("class", "list-group-item");
                                $('#navtab-3').attr("class", "list-group-item active");

                                str = '0000000000' + parseInt(cevap.d) * 1000;
                                str = str.substring(str.length - 10, str.length);
                                str = str + '0000000000';
                                var yuklemeSonuc = obj.KrediYukle(str);

                                if (yuklemeSonuc.substring(0, 2) != 'OK') {
                                    alert("Kartınıza yükleme sırasında bir hata ile karşılaşıldı. Lütfen belediye yetkilisi ile irtibata geçiniz.");
                                    bitti();
                                    return false;
                                }

                                $('#adim').text("ADIM 3");
                                document.getElementById("baslik3").innerHTML = cevap.d + " Kontör yüklenerek işlem tamamlandı, kartınızı alabilirsiniz. <br/>7 gün içerisinde avans su tutarını ödemediğiniz taktirde gecikme zammı uygulanacaktır.";
                                yukleniyor.gizle();

                                window.setTimeout(function () {
                                    yukleniyor.goster();
                                    window.location.href = "default.aspx";
                                }, 10000);
                            }
                             else if (cevap.d == "-2") {

                                 $('#formBody1').css("display", "none");
                                 $('#formBody2').css("display", "none");
                                 $('#formBody3').css("display", "");

                                 $('#devam1').css("display", "none");
                                 $('#devam2').css("display", "none");
                                 $('#devam3').css("display", "");

                                 $('#adim').text("ADIM 3");

                                 $('#navtab-1').attr("class", "list-group-item");
                                 $('#navtab-2').attr("class", "list-group-item");
                                 $('#navtab-3').attr("class", "list-group-item active");

                                 document.getElementById("baslik3").innerHTML = "Aboneliğinize ait ödenmemiş avans kredi yüklemesi bulunmaktadır. Yükleme yapılamaz.";
                                 yukleniyor.gizle();
                                 window.setTimeout(function () {
                                     yukleniyor.goster();
                                     window.location.href = "default.aspx";
                                 }, 10000);
                             }
                             else {
                                 alert("İşlem Yapılamadı. Belediye personeli ile iletişime geçiniz.");
                                 yukleniyor.gizle();
                             }
                         },
                        error: function (cevap) {
                            alert("İşlem Yapılamadı. Belediye personeli ile iletişime geçiniz.");
                            yukleniyor.gizle();
                        }
                    });
                }
            });


        }

        function bitti() {
            yukleniyor.goster();
            window.location.href = "default.aspx";
        }

    </script>

    <div class="portlet light bordered" id="form_wizard_1" style="width: 80%; margin: auto;">
        <div class="portlet-title">
            <div class="caption">
                <i class=" icon-layers font-red"></i>
                <span class="caption-subject font-red bold uppercase">Avans Kredi Yükleme -                   
                    <span id="adim" class="step-title">Adım 1</span>
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

                    <div id="formBody2" class="form-body" style="display: none;">

                        <div class="tab-pane active" id="tab2" style="text-align: center;">
                            <h4 id="baslik2" class="block"></h4>
                        </div>

                    </div>

                    <div id="formBody3" class="form-body" style="display: none;">

                        <div class="tab-pane active" id="tab3" style="text-align: center;">
                            <h4 id="baslik3" class="block"></h4>
                        </div>

                    </div>

                    <div class="form-actions" style="text-align: center;">

                        <input id="devam1" onclick="kartOku();" type="button" value="Devam Et" class="altButon" />
                        <input id="devam2" onclick="avansYukle();" type="button" value="Yükle" class="altButon" style="display: none;" />
                        <input id="devam3" onclick="bitti();" type="button" value="Bitti" class="altButon" style="display: none;" />

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- sol nav -->
    <div class="list-group">
        <a id="navtab-1" class="list-group-item active">1. Kart Okuma</a>
        <a id="navtab-2" class="list-group-item">2. Avans Yükleme</a>
        <a id="navtab-3" class="list-group-item">3. İşlem Sonucu</a>
    </div>
    <!-- sol nav -->

</asp:Content>
