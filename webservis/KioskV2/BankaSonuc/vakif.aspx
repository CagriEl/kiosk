<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="vakif.aspx.cs" Inherits="kiosk.BankaSonuc.vakif" %>
<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server">
</asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">
    
    <link href="../css/reset.css" rel="stylesheet" />
    
    <link href="../css/font-awesome.css" rel="stylesheet" />
    <link href="../css/bootstrap.min.css" rel="stylesheet" />
    <link href="../css/uniform.default.css" rel="stylesheet" />
    <link href="../css/bootstrap-switch.min.css" rel="stylesheet" />
    <link href="../css/select2.min.css" rel="stylesheet" />
    <link href="../css/select2-bootstrap.min.css" rel="stylesheet" />    
    <link href="../css/components.min.css" rel="stylesheet" />
    <link href="../css/plugins.min.css" rel="stylesheet" />
    <link href="../css/sweetalert.css" rel="stylesheet" />
    
    <link href="../css/kiosk.css" rel="stylesheet" />   


    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.slimscroll.min.js"></script>
    <script src="../js/jquery.blockui.min.js"></script>
    <script src="../js/jquery.uniform.min.js"></script>
    <script src="../js/bootstrap-switch.min.js"></script>
    <script src="../js/select2.full.min.js"></script>
    <script src="../js/jquery.validate.min.js"></script>
    <script src="../js/additional-methods.min.js"></script>
    <script src="../js/jquery.bootstrap.wizard.min.js"></script>
    <script src="../js/app.min.js"></script>
    <script src="../js/form-wizard.min.js"></script>
    <%--<script src="js/sweetalert-dev.js"></script>--%>
    <script src="../js/jquery.inputmask.bundle.min.js"></script>
    <script src="../js/form-input-mask.min.js"></script>
   
    <script src="../js/kiosk.js"></script>
    
    <script type="text/javascript">
        document.onkeydown = function () {
            if (window.event && window.event.keyCode == 116) {
                alert("Lütfen F5 tuşunu Kullanmayınız...");
                return false;
            }
            else if (window.event && window.event.keyCode == 8) {
                alert("Lütfen Backspace tuşunu Kullanmayınız...");
                return false;
            }
        }

        $(document).ready(function () {

            $('#backButton').css("display", "none");


            $('#navtab-1').attr("class", "list-group-item");
            $('#navtab-2').attr("class", "list-group-item");
            $('#navtab-3').attr("class", "list-group-item");
            $('#navtab-4').attr("class", "list-group-item");
            $('#navtab-5').attr("class", "list-group-item active");


            $.ajax({
                type: "POST",
                url: "vakif.aspx/odemeyiTamamla",
                contentType: "application/json",
                dataType: "json",
                success: function (cevap) {
                    
                    if (cevap.d.sonucKodu == "1001") {

                        yukleniyor.goster("Lütfen bekleyiniz..", {
                            onShow: function () {
                                var harcanan = cevap.d.suMakbuzlar[0].harcananSu;
                                var aboneNo1 = cevap.d.suMakbuzlar[0].aboneNo;

                                var obj = new ActiveXObject("MSCInterface.Operations");
                                str = '0000000000' + harcanan * 1000;
                                str = str.substring(str.length - 10, str.length);
                                str = str + '0000000000';
                                var yuklemeSonuc = obj.KrediYukle(str);

                                if (yuklemeSonuc.substring(0, 2) != 'OK') {
                                    alert("Kartınıza yükleme sırasında bir hata ile karşılaşıldı. Lütfen belediye yetkilisi ile irtibata geçiniz.");
                                    window.location.href = "../default.aspx";
                                    return false;
                                }

                                $('#islemSonucBanka').text('İşlem başarılı, kartınızı alabilirsiniz.');
                                yazdir("BİLECİK BELEDİYE BAŞKANLIĞI", aboneNo1, harcanan);
                                yukleniyor.gizle();

                                window.setTimeout(function () {
                                    window.location.href = "../default.aspx";
                                }, 5000);

                            }
                        });

                    }
                    else {
                        $('#islemSonucBanka').html('İşlem Başarısız. <br/>' + cevap.d.sonucAciklamasi);

                        window.setTimeout(function () {
                            window.location.href = "../default.aspx";
                        }, 5000);
                    }

                },
                error: function (cevap) {
                    alert("İşlem Yapılamadı. Belediye personeli ile iletişime geçiniz.");
                }
            });
            
        });

        function yazdir(belediyeAdi, aboneNo, miktar) {

            var devam = confirm("Bilgi fişi istiyor musunuz?");
            if (devam == true) {
                //var mywindow = window.open('', 'PRINT', 'height=400,width=600');

                //mywindow.document.write('<html><head><title>Bilgi Fişi</title>');
                //mywindow.document.write('</head><body style="margin:0px;height:9cm;width:8cm;text-align:center;">');
                //mywindow.document.write('<h1>' + belediyeAdi + '</h1>');
                //mywindow.document.write('<h2>Abone No: ' + aboneNo + '</h2>');
                //mywindow.document.write('<h2>Yüklenen Miktar: ' + miktar + '</h2>');
                //mywindow.document.write('<br/><h2>' + tarihGetir() + '</h2>');
                //mywindow.document.write('<h2>Bilgilendirme fişidir</h2>');
                //mywindow.document.write('</body></html>');

                //mywindow.document.close();
                //mywindow.focus();

                //mywindow.print();
                //mywindow.close();

                //return true;

                var x = window.open('../print.aspx?aboneno=' + aboneNo + '&miktar=' + miktar + '&beladi=' + belediyeAdi, 'bilgifisi', 'width=200, height=200', "_blank");
                x.blur();
                window.focus();
            } 
        }

        function tarihGetir() {
            var currentdate = new Date();
            var datetime = currentdate.getDate() + "/"
                            + (currentdate.getMonth() + 1) + "/"
                            + currentdate.getFullYear() + " - "
                            + currentdate.getHours() + ":"
                            + currentdate.getMinutes();
            return datetime;
        }

    </script>

    <div style="width:100%;text-align:center;font-size:25px;padding-top:50px;">
        <span id="islemSonucBanka">Lütfen bekleyiniz ve kartınızı ilgili alandan almayınız..</span>
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

</asp:Content>


