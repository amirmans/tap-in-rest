
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="keywords" content="" />
        <title>Tap In </title>
        <?php $this->load->view('v_head'); ?>
    </head>
    <body>

        <div class="loading"><img src="<?php echo base_url('assets/img/loading.gif'); ?>" alt="loading-img"></div>
        <div class="content">
            <div class="container-mail">
                <div class="mailbox clearfix">
                    <div class="container-mailbox">

                        <div class="col-lg-3 col-md-4 padding-0">
                            <div class="row order-menu">
                                <div class="col-md-1"></div> 
                                <div class="col-md-5"><span class="order_text"><h5>ORDERS</h5></span></div> 
                                <div class="col-md-6 text-right"><select class="selectpicker" style="margin: 10px 24px;">
                                        <option>All</option>
                                    </select>   </div> 
                            </div>
                            <ul class="order-list">




                                <?php for ($i = 0; $i < count($orderlist); $i++) {
                                    ?>
                                    <li onclick="display_order_detail('<?php echo $orderlist[$i]['order_id']; ?>')">
                                        <a href="javascript:void(0)"  id="order_id_<?php echo $orderlist[$i]['order_id']; ?>" class="item clearfix <?php
                                        if ($i == 0) {
                                            echo 'active_detail_order';
                                        }
                                        ?>" >
                                            <img src="<?php echo base_url('assets/img/ic_error@3x.png'); ?>" alt="img" class="img">
                                            <span class="from">#<?php echo $orderlist[$i]['order_id']; ?></span>
                                            <span class="from" ><?php echo $orderlist[$i]['nickname']; ?></span>
                                            <span class="date">9 items</span>
                                            <span class="time"><?php echo time_elapsed_string($orderlist[$i]['date']); ?></span>
                                        </a>
                                    </li>
                                <?php } ?>


                            </ul>
                        </div>

                        <div class="chat col-lg-9 col-md-8 padding-0 order-detail_view" id="order_view" >


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
                                                <td><?php echo $order_detail[$i]['name']; ?>  </td>
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
                        </div>

                    </div>

                </div>

            </div>
            <?php $this->load->view('v_footer'); ?>

        </div>




        <?php $this->load->view('v_script'); ?>


        <script>
            function display_order_detail(order_id)
            {

                var param = {order_id: order_id};
                $.post("<?php echo base_url('index.php/site/order_view') ?>", param)
                        .done(function (data) {
                            data = jQuery.parseJSON(data);
                            $("#order_view").html(data['order_view']);

                            $(".active_detail_order").removeClass("active_detail_order");
                            $("#order_id_"+order_id).addClass("active_detail_order")

                        });
            }
        </script>





    </body>
</html>