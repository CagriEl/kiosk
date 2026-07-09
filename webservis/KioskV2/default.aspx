<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="default.aspx.cs" Inherits="kiosk._default" %>
<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server">
</asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">
    
    <div style="text-align:center;margin-bottom:30px;">
        <img src="images/belediyeLogo.png" style="width:150px;" />
    </div>

    <div style="width:90%;text-align:center;margin:auto;">
        
        <table style="width:100%">
            <tr>
                <td style="width:33%;">
                    <div class="masterMenu" onclick="avans();">
                        Su Avans Yükleme
                    </div>
                </td>
                <td style="width:33%; float:center;">
                    <div class="masterMenu" onclick="kontor();">
                        Su Kontör Yükleme
                    </div>
                </td>
               <!-- <td style="width:33%">
                    <div class="masterMenu" onclick="ebel();">
                        e-Belediye İşlemleri
                    </div>
                </td>-->
            </tr>
            <tr>
                <td style="width:33%;">
                    <div class="altMenu" onclick="avans();">
                        <span id="avansBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>
                <td style="width:33%;float:center;">
                    <div class="altMenu" onclick="kontor();">
                        <span id="kontorBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>
                <!--<td style="width:33%">
                    <div class="altMenu" onclick="ebel();">
                        <span id="eBelBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>-->
            </tr>
            <!-- alt kısım -->
           <!-- <tr>
                <td style="width:33%;padding-top:8px;">
                    <div class="masterMenu" onclick="kentRehberi();">
                        Kent Rehberi Haritası
                    </div>
                </td>
                <td style="width:33%; float:center;">
                    <div class="masterMenu" onclick="mezarlik();">
                        Mezarlık Bilgi Sistemi
                    </div>
                </td>
                <td style="width:33%">
                    <div class="masterMenu" onclick="ecap();">
                        e-Çap
                    </div>
                </td>
            </tr>
            <tr>
                <td style="width:33%;">
                    <div class="altMenu" onclick="kentRehberi();">
                        <span id="kentRehberiBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>
                <td style="width:33%;float:center;">
                    <div class="altMenu" onclick="mezarlik();">
                        <span id="mezarlikBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>
                <td style="width:33%">
                    <div class="altMenu" onclick="ecap();">
                        <span id="eCapBilgi" runat="server" class="menuAltYazi"></span>                        
                    </div>
                </td>
            </tr>-->
        </table>

    </div>

    <script type="text/javascript">

        function avans() {
            UrlExists('avansYukleme.aspx', function (status) {
                if (status === 200) {
                    yukleniyor.goster();
                    window.location.href = "avansYukleme.aspx";
                }
                else if (status === 404) {
                    baglaniyor.goster();
                    setDate();
                }
            });
        }

        function kontor() {
            UrlExists('kontorYukleme.aspx', function (status) {
                if (status === 200) {
                    yukleniyor.goster();
                    window.location.href = "kontorYukleme.aspx";
                }
                else if (status === 404) {
                    baglaniyor.goster();
                    setDate();
                }
            });
        }

        function ebel() {
            yukleniyor.goster();
            window.location.href = 'ebelediye.aspx'
        }

        function kentRehberi() {
            yukleniyor.goster();
            window.location.href = 'kentRehberi.aspx'
        }

        function mezarlik() {
            yukleniyor.goster();
            window.location.href = 'mezarlik.aspx'
        }

        function ecap() {
            yukleniyor.goster();
            window.location.href = 'ecap.aspx'
        }

</script>

</asp:Content>

