<?php
/*
* 画像投稿のコントローラー
*/

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Posted_photos;
use App\Point;
use App\Tags;
use App\User;
use App\Http\Controllers\LikesController;




class ImagesController extends Controller
{

    /*=UserフロントPOST=*/
    
    public function post_img_judge($get_input_id){

    $img_post = $_POST['img_post'];
    if(session()->has('img_token')){
        $session_img = session()->get('img_token');
        if($img_post!=$session_img){
            $this->img_upload($get_input_id);
            session()->put(['img_token'=>$img_post]);
            }
        }else{
        $this->img_upload($get_input_id);
        session()->put(['img_token'=>$img_post]);
        }
    }

    //画像の取り出し方
    // $contents = Storage::disk('s3')->get('face2.jpg');  

    private function img_upload($get_input_id){
        $sample_data = $_POST['files'];
        $img = $_POST['files'];
        $file_type = $_POST['file_type'];
        $img = str_replace('data:'.$file_type.';base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $fileData = base64_decode($img);

        $date = date('Y-m-d H:i:s');
        $folder_name=$date;
        $file_name=$date.'_'.str_random(16);
        $result=Storage::disk('s3')->directories('public/Photos');
        // if(count(Storage::disk('s3')->directories('public/'))==0){
        //     Storage::disk('s3')->makeDirectory($folder_name);
        // }

        $dir_flg=0;
        foreach($result as $val){
            $cnt_img = count(Storage::disk('s3')->allFiles($val));
            if($cnt_img<30){
                $dir=$val;
                $dir_flg=1;
            }
        }
        if($dir_flg==1){
                Storage::disk('s3')->put($dir.'/'.$file_name.'.png', $fileData);
            }else{
                Storage::disk('s3')->makeDirectory('public/Photos/'.$folder_name);
                Storage::disk('s3')->put($folder_name.'/'.$file_name.'.png', $fileData);
            }
        //*新規投稿画像をDBに保存
        $new_posted_photos = new Posted_photos;
        $new_posted_photos->user_id = $get_input_id;
        $new_posted_photos->photo_description = $_POST['description'];
        $new_posted_photos->tag = $_POST['post_tag'];
        $new_posted_photos->photo_name = $_POST['post_name'];
        $new_posted_photos->photo_size = $_POST['photo_size'];
        $new_posted_photos->photo_price = $_POST['photo_price'];
        
        $new_posted_photos->file_name = $file_name;

        if($dir_flg==1){
            $new_posted_photos->photo_path = str_replace('public/Photos/', '', $dir);
        }else{
            $new_posted_photos->photo_path = $folder_name;
        };
        $new_posted_photos->save();

        $vtag=$_POST['post_tag'];
        $tag_array = [];
        $tag_array = explode('#',$vtag);
        foreach($tag_array as $tag_val){
            if (0 == strcmp($tag_val, '')){
                continue;
            }
            if(Tags::where('tag_name',$tag_val)->exists()==true){
                $tag_id = Tags::where('tag_name',$tag_val)->value('id');
                $tag_cnt = Tags::where('tag_name',$tag_val)->value('tag_cnt');
                $change_cnt = Tags::where('id',$tag_id)->first();
                $change_cnt->tag_cnt= $tag_cnt + 1;
                $change_cnt->save();
            continue;
            }
            $new_tags = new Tags;
            $new_tags->tag_name=$tag_val;
            $new_tags->tag_cnt=1;
            $new_tags->save();
        }
        
        return $get_input_id;
    }


    

    public function post_data_get($id){//投稿のデータ
        $img_post_data = [];
        $input_exists = User::where('user_id', $id)->exists();
        if($input_exists != true) exit;//idがなければ処理停止

        $get_my_user_id = session()->get('user_id');
        $get_my_id = User::where('user_id',$get_my_user_id)->value('id');//My内部ID
        $get_input_id = User::where('user_id',$id)->value('id');//内部ID
       if($get_my_id == $get_input_id){//ログインユーザのみ見せたいデータがあれば。
           
       }
       $img_post_data = $this->mediation_post_data($get_input_id);

        
     return $img_post_data;
    }
    public function post_data_post($id){
        //post_image_page.vueからaxiosで取得したID
        $input_exists = User::where('user_id', $id)->exists();
        if($input_exists != true) exit;//idがなければ処理停止
        $get_input_id = User::where('user_id',$id)->value('id');//内部ID

        $photo_result=$this->post_img_judge($get_input_id);
        return $photo_result;
      

    }
    //画像データ送受信のjsonの整形
    private function mediation_post_data($get_input_id){
        $send_img_info = [];
        $data = Posted_photos::where('user_id',$get_input_id)->get();
        //sortとかここでできまするね
        foreach($data as $val => $key){
            $send_img_info[$val] = [$val => $key];
        }
        return $send_img_info;
    }
    //詳細表示のためのメソッド ##search_page.vueで使用
    public function get_details_info($id){
            //$id =>photo_id
            //getしたい情報::user_id,likes_cnt,like_id,like_stauts,Id,user_name,icon_path
    //投稿ユーザの情報取得
    $input_exists = Posted_photos::where('photo_id', $id)->exists();
    if($input_exists != true) exit;//idがなければ処理停止
    $photo_user_id = Posted_photos::where('photo_id', $id)->value('user_id');
    $input_user_exists = User::where('id', $photo_user_id)->exists();
    if($input_user_exists != true) exit;//idがなければ処理停止
    $return_arr = User::where('id', $photo_user_id)->get([
        'user_id',
        'id',
        'icon_path',
        'icon_name',
        'user_name'
        ]);
    $likes = LikesController::likes_allocation($id);//いいね情報GET
    $return_arr[0]['likes_cnt'] = $likes['likes_cnt'];
    $return_arr[0]['like_id'] = $likes['like_id'];
    $return_arr[0]['like_stauts'] = $likes['like_stauts'];
    
return $return_arr;
        }

/*
*購入された画像のデータを送信
*/
    public function get_buy_img(){
        $seller_photo_id = $_POST['photo_id'];
        $buyer_id = $_POST['user_id'];
        $user_judge=Point::where('photo_id',$seller_photo_id)->value('user_id');
        if($buyer_id!=$user_judge)exit;

        $file_path = $_POST['photo_path'];
        $file_name = $_POST['file_name'];
        $contents= Storage::disk('s3')->get('public/Photos/'.$file_path.'/'.$file_name.'.png');
        $fileData = base64_encode($contents);

        return $fileData;
        // $path = Storage::disk('s3')->url('public/Photos/'.$file_path.'/'.$file_name.'.png');
        // return $path;
    }

/* ====================================================================
使い回しメソッド
======================================================================*/
    //*Userモデルでオブジェクトを配列に変換させる
    public function utf_chg($uni_arr){
        $utf8_arr = array();
        $utf8_arr = User::de($uni_arr);
        return $utf8_arr;
    }


}
