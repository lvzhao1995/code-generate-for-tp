<template>
    <div>
        <Form :model="formData" @submit.native.prevent="save" :label-width="80">
            {{curd_form_group}}
            <FormItem>
                <Button type="primary" html-type="submit" :loading="formLoading">保存</Button>
                <Button @click="$router.go(-1);" style="margin-left:10px;">返回</Button>
            </FormItem>
        </Form>
    </div>
</template>
<script>
    import { mapMutations } from "vuex";
    export default {
        data() {
            return {
                formData:{{curd_form_field}},
                formLoading: false
            };
        },
        created() {
        },
        methods: {
            ...mapMutations(["closeTag"]),
            save() {
                this.formLoading = true;
                this.$axios.post("/admin/{{controller_name}}/add", this.formData).then(res => {
                    this.formLoading = false;
                    if (res.code == 1) {
                        this.$Message.success("操作成功");
                        this.closeTag(this.$route);
                    } else {
                        this.$Message.error(res.msg);
                    }
                });
            }
        }
    };
</script>