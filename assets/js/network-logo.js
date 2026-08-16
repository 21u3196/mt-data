function showNetworkLogo()
{
    let network =
    document.getElementById(
        "network"
    ).value;

    let logo =
    document.getElementById(
        "networkLogo"
    );

    if(network === "MTN")
    {
        logo.src =
        "../assets/images/networks/mtn.png";
    }

    else if(network === "Airtel")
    {
        logo.src =
        "../assets/images/networks/airtel.png";
    }

    else if(network === "Glo")
    {
        logo.src =
        "../assets/images/networks/glo.png";
    }

    else if(network === "9mobile")
    {
        logo.src =
        "../assets/images/networks/9mobile.png";
    }
}