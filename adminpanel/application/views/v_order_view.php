
<div class="invoice invoice-row">

    <div class="line row">
        <div class="col-md-6 col-xs-6 padding-0 text-left">
            <h4><span class="badge"><i class="fa fa-exclamation"></i> </span> NEW ORDER</h4>
            <h2>#<?php echo $orderlist[0]['order_id']; ?> <span class="lowlighter">For</span> <?php echo $orderlist[0]['nickname']; ?></h2>
            <span class="time">Ordered <?php echo time_elapsed_string($orderlist[0]['date']); ?></span>
        </div>
        <div class="col-md-6 col-xs-6 padding-0 text-right">


        </div>
    </div>


    <input type="hidden" name="order_id" id="order_id" value="<?php echo encrypt_string($orderlist[0]['order_id']); ?>" />
    <table class="table">
        <thead class="title">
            <tr>
                <td>PRODUCT</td>
                <td>PRICE</td>
                <td>QUANTITY</td>
                <td class="text-right">TOTAL</td>
            </tr>
        </thead>
        <tbody>

            <?php for ($i = 0; $i < count($order_detail); $i++) {
                ?>
                <tr>
                    <td><?php echo $order_detail[$i]['name']; ?> </td>
                    <td>$ <?php echo $order_detail[$i]['price']; ?></td>
                    <td><?php echo $order_detail[$i]['quantity']; ?></td>
                    <td class="text-right">$ <?php echo ($per_item_total[$i] = $order_detail[$i]['price'] * $order_detail[$i]['quantity']); ?></td>
                </tr>

            <?php } ?>

            <tr>
                <td></td>
                <td></td>
                <td class="text-right"></td>
                <td class="text-right">TOTAL<h4 class="total"><input type="hidden" name="order_amount" id="order_amount" value="<?php echo array_sum($per_item_total); ?>"  /> $ <?php echo array_sum($per_item_total); ?></h4></td>
            </tr>
        </tbody>
    </table>

    <div class="bottomtext" style="text-align: center ">

        <a id="button3" href="#" class=" btn btn-primary " data-toggle="modal" data-target="#myModal" style=" font-size: 20px;">
            APPROVE
        </a>
        &nbsp;
        &nbsp;
        &nbsp;

        <a id="" href="#" class=" btn btn-danger " style=" font-size: 20px;">
            REJECT
        </a>



    </div>
         <script>
                                    document.querySelector('#button3').onclick = function () {

                                        $("#button3").html('APPROVE..');

                                        var order_id = $("#order_id").val();
                                        var amout = $("#order_amount").val();
                                        var param = {order_id: order_id};
                                        $.post("<?php echo base_url('index.php/site/payment') ?>", param)
                                                .done(function (data) {
                                                    data = jQuery.parseJSON(data);
                                                    $("#button3").html('APPROVED');

                                                    if (data['status'] == '1')
                                                    {
                                                        
                                                        swal("$" + data['amount'], "Your payment has been successfully processed", "success");
                                                    } else {
                                                        swal("$" + amout, "Something went wrong", "error");
                                                    }


                                                });
                                    };


                                </script>
</div>