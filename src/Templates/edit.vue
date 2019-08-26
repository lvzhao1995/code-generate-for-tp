<template>
    <div>
        <Form :model="formData" @submit.native.prevent="save" :label-width="80">
            {{curd_form_group}}
            <FormItem>
                <Button type="primary" html-type="submit" :loading="formLoading">保存</Button>
            </FormItem>
        </Form>
    </div>
</template>
<script>
    import editPage from "../../mixins/editPage";
    export default {
        data() {
            return {
                formData:{{curd_form_field}},
                formLoading: false,
                editUrl:"/admin/{{controller_name}}/edit"
            };
        },
        mixins:[editPage]
    };
</script>