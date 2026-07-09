<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="kentRehberi.aspx.cs" Inherits="kiosk.kentRehberi" %>
<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server"></asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">
    
    <iframe class="innerFrame" src="http://ims.bilecik.bel.tr/Projects/BILECIK/Pages/KRH.aspx"></iframe>

    <script type="text/javascript">
        $('.innerFrame').load(function () {
          
        })
    </script>

</asp:Content>
